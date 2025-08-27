"""
VirusTotal API Router
Endpoints for VirusTotal analysis and threat intelligence
"""

import logging
import asyncio
import base64
from typing import Dict, Any, List, Optional
from datetime import datetime
from urllib.parse import quote_plus

from fastapi import APIRouter, HTTPException, Depends, BackgroundTasks, UploadFile, File, Form, Query
from fastapi.responses import JSONResponse

from api.clients import VirusTotalClient, VirusTotalAPIError
from api.schemas import (
    VirusTotalFileRequest, VirusTotalURLRequest, VirusTotalHashRequest,
    VirusTotalDomainRequest, VirusTotalIPRequest, VirusTotalSearchRequest,
    VirusTotalAnalysisResult, VirusTotalBulkResult, VirusTotalServiceInfo
)
from api.config import get_settings

logger = logging.getLogger(__name__)

# Create router
router = APIRouter(prefix="/virustotal", tags=["VirusTotal"])

# Dependency to get VirusTotal client
async def get_virustotal_client():
    """Dependency to create and manage VirusTotal client"""
    settings = get_settings()
    
    if not settings.virustotal_api_key:
        raise HTTPException(
            status_code=500, 
            detail="VirusTotal API key not configured"
        )
    
    client = VirusTotalClient(settings.virustotal_api_key)
    await client._create_session()
    try:
        yield client
    finally:
        await client._close_session()

# Utility functions
def _convert_vt_response_to_analysis_result(vt_data: Dict[str, Any], resource_type: str) -> VirusTotalAnalysisResult:
    """Convert VirusTotal API response to our unified format"""
    try:
        data = vt_data.get('data', {})
        attributes = data.get('attributes', {})
        
        # Extract analysis stats
        stats = attributes.get('last_analysis_stats', {})
        analysis_stats = None
        if stats:
            from api.schemas.virustotal_schemas import AnalysisStats
            analysis_stats = AnalysisStats(**stats)
        
        # Extract engine results
        engines = attributes.get('last_analysis_results', {})
        
        # Get analysis date
        analysis_date = attributes.get('last_analysis_date')
        if analysis_date:
            analysis_date = datetime.fromtimestamp(analysis_date)
        
        return VirusTotalAnalysisResult(
            resource_type=resource_type,
            resource_id=data.get('id', ''),
            status="completed",
            stats=analysis_stats,
            engines=engines,
            attributes=attributes,
            analysis_date=analysis_date,
            reputation=attributes.get('reputation'),
            permalink=f"https://www.virustotal.com/gui/{resource_type.replace('ip', 'ip-address')}/{data.get('id', '')}"
        )
    except Exception as e:
        logger.error(f"Error converting VirusTotal response: {e}")
        raise HTTPException(status_code=500, detail="Error processing VirusTotal response")

# Health and Status Endpoints
@router.get("/health", response_model=VirusTotalServiceInfo)
async def virustotal_health(client: VirusTotalClient = Depends(get_virustotal_client)):
    """Check VirusTotal service health and API key validity"""
    try:
        async with client:
            is_connected = await client.test_connection()
            stats = client.get_stats()
            
            return VirusTotalServiceInfo(
                status="healthy" if is_connected else "unhealthy",
                rate_limit=stats.get("rate_limit", {}),
                daily_quota_usage=stats.get("rate_limit", {}).get("daily_usage_percentage"),
                total_requests=stats.get("total_requests", 0)
            )
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"VirusTotal health check failed: {e}")
        raise HTTPException(status_code=500, detail="Health check failed")

# File Analysis Endpoints
@router.post("/files/analyze", response_model=VirusTotalAnalysisResult)
async def analyze_file_upload(
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    password: Optional[str] = Form(None),
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Upload and analyze a file"""
    try:
        if file.size > 32 * 1024 * 1024:  # 32MB limit
            raise HTTPException(status_code=413, detail="File too large (max 32MB)")
        
        # Save uploaded file temporarily
        import tempfile
        import os
        
        with tempfile.NamedTemporaryFile(delete=False) as temp_file:
            content = await file.read()
            temp_file.write(content)
            temp_file_path = temp_file.name
        
        try:
            async with client:
                # Submit file for analysis
                result = await client.analyze_file(temp_file_path)
                analysis_id = result.get('data', {}).get('id')
                
                if not analysis_id:
                    raise HTTPException(status_code=500, detail="Failed to submit file for analysis")
                
                # Return analysis result
                return VirusTotalAnalysisResult(
                    resource_type="file",
                    resource_id=analysis_id,
                    status="processing",
                    permalink=f"https://www.virustotal.com/gui/file-analysis/{analysis_id}"
                )
        finally:
            # Cleanup temporary file
            try:
                os.unlink(temp_file_path)
            except:
                pass
                
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"File analysis failed: {e}")
        raise HTTPException(status_code=500, detail="File analysis failed")

@router.get("/files/{file_hash}", response_model=VirusTotalAnalysisResult)
async def get_file_analysis(
    file_hash: str,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get file analysis results by hash"""
    try:
        async with client:
            result = await client.get_file_analysis(file_hash)
            return _convert_vt_response_to_analysis_result(result, "file")
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="File not found in VirusTotal database")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get file analysis failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve file analysis")

@router.get("/files/{file_hash}/behaviours")
async def get_file_behaviours(
    file_hash: str,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get file dynamic analysis (sandbox) results"""
    try:
        async with client:
            result = await client.get_file_behaviours(file_hash)
            return result
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="File behaviours not found")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get file behaviours failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve file behaviours")

# URL Analysis Endpoints
@router.post("/urls/analyze", response_model=VirusTotalAnalysisResult)
async def analyze_url(
    request: VirusTotalURLRequest,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Submit a URL for analysis"""
    try:
        async with client:
            result = await client.analyze_url(request.url)
            analysis_id = result.get('data', {}).get('id')
            
            if not analysis_id:
                raise HTTPException(status_code=500, detail="Failed to submit URL for analysis")
            
            return VirusTotalAnalysisResult(
                resource_type="url",
                resource_id=analysis_id,
                status="processing",
                permalink=f"https://www.virustotal.com/gui/url/{analysis_id}"
            )
            
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"URL analysis failed: {e}")
        raise HTTPException(status_code=500, detail="URL analysis failed")

@router.get("/url-scan", response_model=VirusTotalAnalysisResult)
async def analyze_url_by_string(
    url: str = Query(..., description="URL to analyze"),
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Analyze URL and get results by URL string (convenience endpoint)"""
    try:
        logger.info(f"Starting URL analysis for: {url}")
        
        # First, create URL ID from base64 encoding (this is how VirusTotal identifies URLs)
        url_id = base64.urlsafe_b64encode(url.encode()).decode().rstrip('=')
        logger.info(f"Generated URL ID: {url_id}")
        
        # Try to get existing analysis first
        try:
            logger.info(f"Checking existing analysis for URL ID: {url_id}")
            result = await client.get_url_analysis(url_id)
            logger.info("Found existing URL analysis")
            return _convert_vt_response_to_analysis_result(result, "url")
        except VirusTotalAPIError as e:
            if e.status_code == 404:
                logger.info("No existing analysis found, submitting URL for new analysis")
                # Submit URL for analysis
                submit_result = await client.analyze_url(url)
                logger.info(f"URL submitted successfully: {submit_result}")
                
                # Return processing status
                analysis_id = submit_result.get('data', {}).get('id', url_id)
                return VirusTotalAnalysisResult(
                    resource_type="url",
                    resource_id=analysis_id,
                    status="processing",
                    message="URL submitted for analysis. Please check back in a few minutes.",
                    permalink=f"https://www.virustotal.com/gui/url/{url_id}"
                )
            else:
                logger.error(f"Error checking existing analysis: {e.message}")
                raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
            
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"URL analysis by string failed: {e}")
        raise HTTPException(status_code=500, detail="URL analysis failed")

@router.get("/urls/{url_id}", response_model=VirusTotalAnalysisResult)
async def get_url_analysis(
    url_id: str,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get URL analysis results"""
    try:
        async with client:
            result = await client.get_url_analysis(url_id)
            return _convert_vt_response_to_analysis_result(result, "url")
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="URL not found in VirusTotal database")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get URL analysis failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve URL analysis")

# Domain Analysis Endpoints
@router.get("/domains/{domain}", response_model=VirusTotalAnalysisResult)
async def get_domain_analysis(
    domain: str,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get domain analysis and reputation"""
    try:
        async with client:
            result = await client.get_domain_info(domain)
            return _convert_vt_response_to_analysis_result(result, "domain")
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="Domain not found in VirusTotal database")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get domain analysis failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve domain analysis")

@router.get("/domains/{domain}/subdomains")
async def get_domain_subdomains(
    domain: str,
    limit: int = 40,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get subdomains for a domain"""
    try:
        async with client:
            result = await client.get_domain_subdomains(domain, limit)
            return result
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="Domain subdomains not found")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get domain subdomains failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve domain subdomains")

@router.get("/domains/{domain}/resolutions")
async def get_domain_resolutions(
    domain: str,
    limit: int = 40,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get DNS resolutions for a domain"""
    try:
        async with client:
            result = await client.get_domain_resolutions(domain, limit)
            return result
            
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get domain resolutions failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve domain resolutions")

# IP Address Analysis Endpoints
@router.get("/ip/{ip_address}", response_model=VirusTotalAnalysisResult)
async def get_ip_analysis(
    ip_address: str,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get IP address analysis and reputation"""
    try:
        async with client:
            result = await client.get_ip_info(ip_address)
            return _convert_vt_response_to_analysis_result(result, "ip")
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="IP address not found in VirusTotal database")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get IP analysis failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve IP analysis")

@router.get("/ip/{ip_address}/resolutions")
async def get_ip_resolutions(
    ip_address: str,
    limit: int = 40,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get DNS resolutions for an IP address"""
    try:
        async with client:
            result = await client.get_ip_resolutions(ip_address, limit)
            return result
            
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get IP resolutions failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve IP resolutions")

# Search Endpoints
@router.post("/search")
async def search_virustotal(
    request: VirusTotalSearchRequest,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Search VirusTotal database"""
    try:
        async with client:
            result = await client.search(request.query, request.limit)
            return result
            
    except VirusTotalAPIError as e:
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"VirusTotal search failed: {e}")
        raise HTTPException(status_code=500, detail="Search failed")

# Analysis Status Endpoints
@router.get("/analyses/{analysis_id}")
async def get_analysis_status(
    analysis_id: str,
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Get analysis status and results by analysis ID"""
    try:
        async with client:
            result = await client.get_analysis(analysis_id)
            return result
            
    except VirusTotalAPIError as e:
        if e.status_code == 404:
            raise HTTPException(status_code=404, detail="Analysis not found")
        raise HTTPException(status_code=400, detail=f"VirusTotal API error: {e.message}")
    except Exception as e:
        logger.error(f"Get analysis status failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve analysis status")

# Bulk Analysis Endpoints
@router.post("/bulk/hashes", response_model=VirusTotalBulkResult)
async def bulk_hash_analysis(
    hashes: List[str],
    client: VirusTotalClient = Depends(get_virustotal_client)
):
    """Bulk analysis of file hashes"""
    if len(hashes) > 25:  # Limit to avoid rate limiting issues
        raise HTTPException(status_code=400, detail="Too many hashes (max 25)")
    
    successful = []
    failed = []
    
    try:
        async with client:
            for file_hash in hashes:
                try:
                    result = await client.get_file_analysis(file_hash)
                    analysis_result = _convert_vt_response_to_analysis_result(result, "file")
                    successful.append(analysis_result)
                except VirusTotalAPIError as e:
                    failed.append({
                        "hash": file_hash,
                        "error": e.message,
                        "status_code": e.status_code
                    })
                except Exception as e:
                    failed.append({
                        "hash": file_hash,
                        "error": str(e)
                    })
                
                # Add small delay to respect rate limits
                await asyncio.sleep(0.25)
        
        return VirusTotalBulkResult(
            successful=successful,
            failed=failed,
            total_processed=len(hashes)
        )
        
    except Exception as e:
        logger.error(f"Bulk hash analysis failed: {e}")
        raise HTTPException(status_code=500, detail="Bulk analysis failed")

# Statistics and Info Endpoints
@router.get("/stats")
async def get_virustotal_stats(client: VirusTotalClient = Depends(get_virustotal_client)):
    """Get VirusTotal service usage statistics"""
    try:
        stats = client.get_stats()
        return stats
    except Exception as e:
        logger.error(f"Get VirusTotal stats failed: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve statistics")