"""
Google Custom Search API Router
Endpoints for Google Custom Search functionality
"""

import logging
import asyncio
from typing import Dict, Any, List, Optional
from urllib.parse import urlparse

from fastapi import APIRouter, HTTPException, Depends, BackgroundTasks, Query, Body
from fastapi.responses import JSONResponse

from api.clients import GoogleSearchClient, GoogleSearchAPIError
from api.schemas import (
    GoogleSearchRequest, GoogleImageSearchRequest, GoogleSiteSearchRequest,
    GoogleSearchResult, GoogleSearchSummary, GoogleSearchInfo, GoogleSearchError,
    BulkSearchRequest, BulkSearchResult
)
from api.config import get_settings

logger = logging.getLogger(__name__)

# Create router
router = APIRouter(prefix="/google-search", tags=["Google Custom Search"])

# Dependency to get Google Search client
async def get_google_search_client():
    """Dependency to create and manage Google Search client"""
    settings = get_settings()
    
    if not settings.google_api_key:
        raise HTTPException(
            status_code=500, 
            detail="Google API key not configured"
        )
    
    if not settings.google_cse_id:
        raise HTTPException(
            status_code=500,
            detail="Google Custom Search Engine ID not configured"
        )
    
    # Create client with enhanced rate limiting
    # Use configuration values with fallback to conservative defaults
    calls_per_day = settings.google_calls_per_day
    calls_per_minute = settings.google_calls_per_minute
    
    client = GoogleSearchClient(
        api_key=settings.google_api_key, 
        cse_id=settings.google_cse_id,
        calls_per_day=calls_per_day,
        calls_per_minute=calls_per_minute
    )
    try:
        yield client
    finally:
        # Google client doesn't need explicit cleanup like HTTP sessions
        pass


# Utility functions
def _convert_google_response_to_summary(result: Dict[str, Any], query: str) -> GoogleSearchSummary:
    """Convert Google API response to simplified summary format"""
    search_info = result.get('searchInformation', {})
    items = result.get('items', [])
    
    # Convert items to simplified format
    simplified_items = []
    for item in items:
        simplified_item = {
            'title': item.get('title', ''),
            'link': item.get('link', ''),
            'snippet': item.get('snippet', ''),
            'display_link': item.get('displayLink', ''),
            'formatted_url': item.get('formattedUrl', ''),
            'mime': item.get('mime'),
            'file_format': item.get('fileFormat')
        }
        
        # Add image data if present
        if 'image' in item:
            simplified_item['image'] = item['image']
        
        simplified_items.append(simplified_item)
    
    return GoogleSearchSummary(
        query=query,
        total_results=int(search_info.get('totalResults', '0')),
        search_time=float(search_info.get('searchTime', 0)),
        results_count=len(simplified_items),
        items=simplified_items
    )


# Health check endpoint
@router.get("/health", response_model=Dict[str, str])
async def google_search_health(client: GoogleSearchClient = Depends(get_google_search_client)):
    """Health check for Google Custom Search service"""
    try:
        # Test with minimal search to verify API access
        await client.search("test", num_results=1)
        return {"status": "healthy", "service": "Google Custom Search API"}
    except Exception as e:
        logger.error(f"Google Search health check failed: {e}")
        raise HTTPException(status_code=503, detail=f"Google Search API unavailable: {e}")


# Search endpoints
@router.post("/search", response_model=GoogleSearchSummary)
async def search(
    request: GoogleSearchRequest,
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Perform Google Custom Search"""
    try:
        logger.info(f"Performing Google search for: {request.query}")
        
        result = await client.search(
            query=request.query,
            num_results=request.num_results,
            start_index=request.start_index,
            site_search=request.site_search,
            file_type=request.file_type,
            date_restrict=request.date_restrict,
            exact_terms=request.exact_terms,
            exclude_terms=request.exclude_terms,
            link_site=request.link_site,
            or_terms=request.or_terms,
            related_site=request.related_site,
            rights=request.rights,
            safe=request.safe
        )
        
        return _convert_google_response_to_summary(result, request.query)
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Google search failed: {e}")
        raise HTTPException(status_code=500, detail="Search failed")


@router.get("/search", response_model=GoogleSearchSummary)
async def search_get(
    q: str = Query(..., description="Search query"),
    num: int = Query(default=10, description="Number of results", ge=1, le=10),
    start: int = Query(default=1, description="Start index", ge=1, le=91),
    site: Optional[str] = Query(None, description="Site search restriction"),
    filetype: Optional[str] = Query(None, description="File type filter"),
    dateRestrict: Optional[str] = Query(None, description="Date restriction"),
    exactTerms: Optional[str] = Query(None, description="Exact terms"),
    excludeTerms: Optional[str] = Query(None, description="Exclude terms"),
    safe: str = Query(default="medium", description="Safe search level"),
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Perform Google Custom Search via GET request"""
    try:
        logger.info(f"Performing Google search (GET) for: {q}")
        
        result = await client.search(
            query=q,
            num_results=num,
            start_index=start,
            site_search=site,
            file_type=filetype,
            date_restrict=dateRestrict,
            exact_terms=exactTerms,
            exclude_terms=excludeTerms,
            safe=safe
        )
        
        return _convert_google_response_to_summary(result, q)
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Google search failed: {e}")
        raise HTTPException(status_code=500, detail="Search failed")


@router.post("/image-search", response_model=GoogleSearchSummary)
async def image_search(
    request: GoogleImageSearchRequest,
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Perform Google Image Search"""
    try:
        logger.info(f"Performing Google image search for: {request.query}")
        
        result = await client.image_search(
            query=request.query,
            num_results=request.num_results,
            start_index=request.start_index,
            image_size=request.image_size,
            image_type=request.image_type,
            image_color_type=request.image_color_type,
            image_dominant_color=request.image_dominant_color,
            safe=request.safe
        )
        
        return _convert_google_response_to_summary(result, request.query)
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Image Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Google image search failed: {e}")
        raise HTTPException(status_code=500, detail="Image search failed")


@router.get("/image-search", response_model=GoogleSearchSummary)
async def image_search_get(
    q: str = Query(..., description="Search query"),
    num: int = Query(default=10, description="Number of results", ge=1, le=10),
    start: int = Query(default=1, description="Start index", ge=1, le=91),
    imgSize: Optional[str] = Query(None, description="Image size"),
    imgType: Optional[str] = Query(None, description="Image type"),
    imgColorType: Optional[str] = Query(None, description="Image color type"),
    imgDominantColor: Optional[str] = Query(None, description="Dominant color"),
    safe: str = Query(default="medium", description="Safe search level"),
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Perform Google Image Search via GET request"""
    try:
        logger.info(f"Performing Google image search (GET) for: {q}")
        
        result = await client.image_search(
            query=q,
            num_results=num,
            start_index=start,
            image_size=imgSize,
            image_type=imgType,
            image_color_type=imgColorType,
            image_dominant_color=imgDominantColor,
            safe=safe
        )
        
        return _convert_google_response_to_summary(result, q)
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Image Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Google image search failed: {e}")
        raise HTTPException(status_code=500, detail="Image search failed")


@router.post("/site-search", response_model=GoogleSearchSummary)
async def site_search(
    request: GoogleSiteSearchRequest,
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Search within a specific site"""
    try:
        logger.info(f"Performing site search for '{request.query}' on {request.site}")
        
        result = await client.site_search(
            query=request.query,
            site=request.site,
            num_results=request.num_results,
            start_index=request.start_index
        )
        
        return _convert_google_response_to_summary(result, request.query)
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Site Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Google site search failed: {e}")
        raise HTTPException(status_code=500, detail="Site search failed")


@router.get("/site-search", response_model=GoogleSearchSummary)
async def site_search_get(
    q: str = Query(..., description="Search query"),
    site: str = Query(..., description="Site domain"),
    num: int = Query(default=10, description="Number of results", ge=1, le=10),
    start: int = Query(default=1, description="Start index", ge=1, le=91),
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Search within a specific site via GET request"""
    try:
        logger.info(f"Performing site search (GET) for '{q}' on {site}")
        
        result = await client.site_search(
            query=q,
            site=site,
            num_results=num,
            start_index=start
        )
        
        return _convert_google_response_to_summary(result, q)
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Site Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Google site search failed: {e}")
        raise HTTPException(status_code=500, detail="Site search failed")


@router.post("/bulk-search", response_model=BulkSearchResult)
async def bulk_search(
    request: BulkSearchRequest,
    background_tasks: BackgroundTasks,
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Perform bulk searches (multiple queries)"""
    try:
        logger.info(f"Performing bulk search for {len(request.queries)} queries")
        
        results = {}
        successful_queries = 0
        failed_queries = 0
        
        for query in request.queries:
            try:
                search_result = await client.search(
                    query=query,
                    num_results=request.num_results,
                    site_search=request.site_search,
                    safe=request.safe
                )
                
                results[query] = _convert_google_response_to_summary(search_result, query)
                successful_queries += 1
                
                # Add small delay between requests to respect rate limits
                await asyncio.sleep(0.1)
                
            except GoogleSearchAPIError as e:
                results[query] = GoogleSearchError(
                    error="api_error",
                    message=e.message,
                    status_code=e.status_code
                )
                failed_queries += 1
            except Exception as e:
                results[query] = GoogleSearchError(
                    error="unexpected_error",
                    message=str(e)
                )
                failed_queries += 1
        
        return BulkSearchResult(
            total_queries=len(request.queries),
            successful_queries=successful_queries,
            failed_queries=failed_queries,
            results=results
        )
        
    except Exception as e:
        logger.error(f"Bulk search failed: {e}")
        raise HTTPException(status_code=500, detail="Bulk search failed")


@router.get("/cse-info", response_model=GoogleSearchInfo)
async def get_cse_info(client: GoogleSearchClient = Depends(get_google_search_client)):
    """Get Custom Search Engine information"""
    try:
        info = await client.get_search_info()
        return GoogleSearchInfo(**info)
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Failed to get CSE info: {e.message}")
    except Exception as e:
        logger.error(f"Failed to get CSE info: {e}")
        raise HTTPException(status_code=500, detail="Failed to get CSE info")


@router.get("/rate-limit-status", response_model=Dict[str, Any])
async def get_rate_limit_status(client: GoogleSearchClient = Depends(get_google_search_client)):
    """Get current rate limit status"""
    try:
        status = client.get_rate_limit_status()
        return status
    except Exception as e:
        logger.error(f"Failed to get rate limit status: {e}")
        raise HTTPException(status_code=500, detail="Failed to get rate limit status")


# Raw response endpoints (for advanced users who want full Google API response)
@router.get("/raw-search", response_model=Dict[str, Any])
async def raw_search(
    q: str = Query(..., description="Search query"),
    num: int = Query(default=10, description="Number of results", ge=1, le=10),
    start: int = Query(default=1, description="Start index", ge=1, le=91),
    site: Optional[str] = Query(None, description="Site search restriction"),
    safe: str = Query(default="medium", description="Safe search level"),
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Get raw Google Search API response"""
    try:
        result = await client.search(
            query=q,
            num_results=num,
            start_index=start,
            site_search=site,
            safe=safe
        )
        
        return result
        
    except GoogleSearchAPIError as e:
        raise HTTPException(status_code=400, detail=f"Google Search API error: {e.message}")
    except Exception as e:
        logger.error(f"Raw Google search failed: {e}")
        raise HTTPException(status_code=500, detail="Raw search failed")