"""
Google Custom Search API Schemas
Pydantic models for Google Custom Search requests and responses
"""

from typing import Dict, Any, List, Optional, Union
from datetime import datetime
from pydantic import BaseModel, Field, validator


class GoogleSearchRequest(BaseModel):
    """Google Custom Search request schema"""
    query: str = Field(..., description="Search query string", min_length=1, max_length=500)
    num_results: int = Field(default=10, description="Number of results to return", ge=1, le=10)
    start_index: int = Field(default=1, description="Starting index for results", ge=1, le=91)
    site_search: Optional[str] = Field(None, description="Restrict search to specific site")
    file_type: Optional[str] = Field(None, description="File type filter")
    date_restrict: Optional[str] = Field(None, description="Date restriction (d1, w1, m1, y1)")
    exact_terms: Optional[str] = Field(None, description="Terms that must appear exactly")
    exclude_terms: Optional[str] = Field(None, description="Terms to exclude from results")
    link_site: Optional[str] = Field(None, description="Find pages linking to this site")
    or_terms: Optional[str] = Field(None, description="Terms where any one should match")
    related_site: Optional[str] = Field(None, description="Find pages related to this site")
    rights: Optional[str] = Field(None, description="Copyright/usage rights filter")
    safe: str = Field(default="medium", description="Safe search level")
    
    @validator('safe')
    def validate_safe_search(cls, v):
        allowed_values = ['active', 'medium', 'off']
        if v not in allowed_values:
            raise ValueError(f"safe must be one of {allowed_values}")
        return v
    
    @validator('date_restrict')
    def validate_date_restrict(cls, v):
        if v is None:
            return v
        # Format: d[number], w[number], m[number], y[number]
        import re
        if not re.match(r'^[dwmy]\d+$', v):
            raise ValueError("date_restrict must be in format d1, w1, m1, y1, etc.")
        return v


class GoogleImageSearchRequest(GoogleSearchRequest):
    """Google Image Search specific request schema"""
    image_size: Optional[str] = Field(None, description="Image size filter")
    image_type: Optional[str] = Field(None, description="Image type filter")
    image_color_type: Optional[str] = Field(None, description="Image color type filter")
    image_dominant_color: Optional[str] = Field(None, description="Dominant color filter")
    
    @validator('image_size')
    def validate_image_size(cls, v):
        if v is None:
            return v
        allowed_values = ['icon', 'small', 'medium', 'large', 'xlarge', 'xxlarge', 'huge']
        if v not in allowed_values:
            raise ValueError(f"image_size must be one of {allowed_values}")
        return v
    
    @validator('image_type')
    def validate_image_type(cls, v):
        if v is None:
            return v
        allowed_values = ['clipart', 'face', 'lineart', 'stock', 'photo', 'animated']
        if v not in allowed_values:
            raise ValueError(f"image_type must be one of {allowed_values}")
        return v
    
    @validator('image_color_type')
    def validate_image_color_type(cls, v):
        if v is None:
            return v
        allowed_values = ['color', 'gray', 'mono', 'trans']
        if v not in allowed_values:
            raise ValueError(f"image_color_type must be one of {allowed_values}")
        return v
    
    @validator('image_dominant_color')
    def validate_image_dominant_color(cls, v):
        if v is None:
            return v
        allowed_values = ['black', 'blue', 'brown', 'gray', 'green', 'orange', 
                         'pink', 'purple', 'red', 'teal', 'white', 'yellow']
        if v not in allowed_values:
            raise ValueError(f"image_dominant_color must be one of {allowed_values}")
        return v


class GoogleSiteSearchRequest(BaseModel):
    """Site-specific search request schema"""
    query: str = Field(..., description="Search query string", min_length=1)
    site: str = Field(..., description="Site domain to search within")
    num_results: int = Field(default=10, description="Number of results", ge=1, le=10)
    start_index: int = Field(default=1, description="Starting index", ge=1, le=91)


class SearchInformation(BaseModel):
    """Search information metadata"""
    search_time: float = Field(..., description="Time taken for search in seconds")
    formatted_search_time: str = Field(..., description="Formatted search time")
    total_results: str = Field(..., description="Total number of results")
    formatted_total_results: str = Field(..., description="Formatted total results count")


class SearchItem(BaseModel):
    """Individual search result item"""
    kind: str = Field(..., description="Resource type")
    title: str = Field(..., description="Result title")
    html_title: Optional[str] = Field(None, description="HTML formatted title")
    link: str = Field(..., description="Result URL")
    display_link: str = Field(..., description="Display URL")
    snippet: Optional[str] = Field(None, description="Result snippet")
    html_snippet: Optional[str] = Field(None, description="HTML formatted snippet")
    cached_id: Optional[str] = Field(None, description="Cached page ID")
    formatted_url: Optional[str] = Field(None, description="Formatted URL")
    html_formatted_url: Optional[str] = Field(None, description="HTML formatted URL")
    
    # Image-specific fields
    image: Optional[Dict[str, Any]] = Field(None, description="Image metadata")
    
    # Additional metadata
    mime: Optional[str] = Field(None, description="MIME type")
    file_format: Optional[str] = Field(None, description="File format")


class SearchContext(BaseModel):
    """Search engine context information"""
    title: str = Field(..., description="Custom Search Engine title")


class GoogleSearchResult(BaseModel):
    """Complete Google Search result response"""
    kind: str = Field(..., description="API response type")
    url: Dict[str, str] = Field(..., description="URL information")
    queries: Dict[str, List[Dict[str, Any]]] = Field(..., description="Query information")
    context: Optional[SearchContext] = Field(None, description="Search context")
    search_information: SearchInformation = Field(..., description="Search metadata")
    items: List[SearchItem] = Field(default=[], description="Search result items")


class GoogleSearchSummary(BaseModel):
    """Simplified search result summary"""
    query: str = Field(..., description="Original search query")
    total_results: int = Field(..., description="Total number of results")
    search_time: float = Field(..., description="Search time in seconds")
    results_count: int = Field(..., description="Number of results returned")
    items: List[Dict[str, Any]] = Field(..., description="Simplified result items")


class GoogleSearchInfo(BaseModel):
    """Google Custom Search Engine information"""
    cse_id: str = Field(..., description="Custom Search Engine ID")
    title: str = Field(..., description="CSE title")
    search_time: float = Field(..., description="Last search time")
    formatted_search_time: str = Field(..., description="Formatted search time")
    total_results: str = Field(..., description="Sample total results")
    formatted_total_results: str = Field(..., description="Formatted total results")


class GoogleSearchError(BaseModel):
    """Google Search API error response"""
    error: str = Field(..., description="Error type")
    message: str = Field(..., description="Error message")
    status_code: Optional[int] = Field(None, description="HTTP status code")
    details: Optional[Dict[str, Any]] = Field(None, description="Additional error details")


# Bulk search schemas
class BulkSearchRequest(BaseModel):
    """Bulk search request schema"""
    queries: List[str] = Field(..., description="List of search queries", min_items=1, max_items=10)
    num_results: int = Field(default=5, description="Results per query", ge=1, le=10)
    site_search: Optional[str] = Field(None, description="Site restriction for all queries")
    safe: str = Field(default="medium", description="Safe search level")


class BulkSearchResult(BaseModel):
    """Bulk search result response"""
    total_queries: int = Field(..., description="Total number of queries processed")
    successful_queries: int = Field(..., description="Number of successful queries")
    failed_queries: int = Field(..., description="Number of failed queries")
    results: Dict[str, Union[GoogleSearchSummary, GoogleSearchError]] = Field(
        ..., description="Results keyed by query"
    )


# Export all schemas
__all__ = [
    'GoogleSearchRequest',
    'GoogleImageSearchRequest', 
    'GoogleSiteSearchRequest',
    'SearchInformation',
    'SearchItem',
    'SearchContext',
    'GoogleSearchResult',
    'GoogleSearchSummary',
    'GoogleSearchInfo',
    'GoogleSearchError',
    'BulkSearchRequest',
    'BulkSearchResult'
]