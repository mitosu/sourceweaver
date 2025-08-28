"""
Advanced OSINT Dorking Router
Endpoints for comprehensive OSINT analysis using dorking templates
"""

import logging
from typing import Dict, Any, List, Optional

from fastapi import APIRouter, HTTPException, Depends, Query
from pydantic import BaseModel, Field

from api.clients import GoogleSearchClient
from api.services.dorking_service import DorkingService
from api.config import get_settings

logger = logging.getLogger(__name__)

# Create router
router = APIRouter(prefix="/dorking", tags=["Advanced OSINT Dorking"])


class DorkingAnalysisRequest(BaseModel):
    """Request model for dorking analysis"""
    target: str = Field(..., description="Target to analyze (alias, domain, etc.)")
    target_type: str = Field(..., description="Type of target: alias, domain")
    priority_filter: Optional[str] = Field(None, description="Filter by priority: high, medium, low")
    category_filter: Optional[str] = Field(None, description="Filter by category")
    max_results_per_query: int = Field(default=5, ge=1, le=10, description="Max results per query")


class DorkingAnalysisResponse(BaseModel):
    """Response model for dorking analysis"""
    target: str
    target_type: str
    total_templates_used: int
    analysis_results: Dict[str, Any]
    summary: Dict[str, Any]


# Dependency to get dorking service
async def get_dorking_service():
    """Dependency to create dorking service with Google Search client"""
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
    
    google_client = GoogleSearchClient(settings.google_api_key, settings.google_cse_id)
    service = DorkingService(google_client)
    
    try:
        yield service
    finally:
        # Cleanup if needed
        pass


@router.get("/health")
async def dorking_health():
    """Health check for dorking service"""
    return {"status": "healthy", "service": "Advanced OSINT Dorking"}


@router.post("/analyze", response_model=DorkingAnalysisResponse)
async def analyze_target(
    request: DorkingAnalysisRequest,
    service: DorkingService = Depends(get_dorking_service)
):
    """
    Perform comprehensive OSINT analysis using advanced dorking templates
    """
    try:
        logger.info(f"Starting dorking analysis for {request.target_type}: {request.target}")
        
        if request.target_type.lower() == "alias":
            results = await service.analyze_alias_comprehensive(
                alias=request.target,
                priority_filter=request.priority_filter,
                category_filter=request.category_filter,
                max_results_per_query=request.max_results_per_query
            )
        elif request.target_type.lower() == "domain":
            results = await service.analyze_domain_comprehensive(
                domain=request.target,
                priority_filter=request.priority_filter,
                max_results_per_query=request.max_results_per_query
            )
        else:
            raise HTTPException(
                status_code=400,
                detail=f"Unsupported target type: {request.target_type}. Supported types: alias, domain"
            )
        
        # Add target_type to results
        results["target_type"] = request.target_type
        results["target"] = request.target
        
        return DorkingAnalysisResponse(**results)
        
    except Exception as e:
        logger.error(f"Dorking analysis failed: {e}")
        raise HTTPException(status_code=500, detail=f"Analysis failed: {str(e)}")


@router.get("/analyze-alias/{alias}")
async def analyze_alias_get(
    alias: str,
    priority: Optional[str] = Query(None, description="Priority filter: high, medium, low"),
    category: Optional[str] = Query(None, description="Category filter"),
    max_results: int = Query(default=5, ge=1, le=10, description="Max results per query"),
    service: DorkingService = Depends(get_dorking_service)
):
    """
    Analyze alias via GET request for quick testing
    """
    try:
        results = await service.analyze_alias_comprehensive(
            alias=alias,
            priority_filter=priority,
            category_filter=category,
            max_results_per_query=max_results
        )
        
        return results
        
    except Exception as e:
        logger.error(f"Alias analysis failed: {e}")
        raise HTTPException(status_code=500, detail=f"Analysis failed: {str(e)}")


@router.get("/templates/{target_type}")
async def get_templates_info(
    target_type: str,
    service: DorkingService = Depends(get_dorking_service)
):
    """
    Get information about available dorking templates
    """
    try:
        if target_type not in ["alias", "domain"]:
            raise HTTPException(
                status_code=400,
                detail="Target type must be 'alias' or 'domain'"
            )
        
        templates_info = service.get_templates_info(target_type)
        categories = service.get_available_categories(target_type)
        
        return {
            "target_type": target_type,
            "available_categories": categories,
            "total_templates": len(templates_info),
            "templates": templates_info
        }
        
    except Exception as e:
        logger.error(f"Failed to get templates info: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to get templates: {str(e)}")


@router.get("/categories/{target_type}")
async def get_categories(
    target_type: str,
    service: DorkingService = Depends(get_dorking_service)
):
    """
    Get available categories for a target type
    """
    try:
        categories = service.get_available_categories(target_type)
        return {
            "target_type": target_type,
            "categories": categories
        }
        
    except Exception as e:
        logger.error(f"Failed to get categories: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to get categories: {str(e)}")


@router.get("/test-single-dork")
async def test_single_dork(
    dork_query: str = Query(..., description="The dork query to test"),
    max_results: int = Query(default=5, ge=1, le=10),
    service: DorkingService = Depends(get_dorking_service)
):
    """
    Test a single dork query for debugging purposes
    """
    try:
        logger.info(f"Testing single dork query: {dork_query}")
        
        result = await service.google_client.search(
            query=dork_query,
            num_results=max_results
        )
        
        return {
            "query": dork_query,
            "total_results": int(result.get('searchInformation', {}).get('totalResults', '0')),
            "returned_items": len(result.get('items', [])),
            "search_time": result.get('searchInformation', {}).get('searchTime', 0),
            "items": result.get('items', [])
        }
        
    except Exception as e:
        logger.error(f"Single dork test failed: {e}")
        raise HTTPException(status_code=500, detail=f"Test failed: {str(e)}")