"""
Alias Search Schemas
Pydantic models for alias/username search in social media platforms
"""

from typing import Dict, Any, List, Optional, Union
from pydantic import BaseModel, Field
from enum import Enum


class SocialPlatform(str, Enum):
    """Supported social media platforms"""
    TWITTER_X = "twitter_x"
    FACEBOOK = "facebook"
    LINKEDIN = "linkedin"
    GITHUB = "github"
    INSTAGRAM = "instagram"
    REDDIT = "reddit"
    YOUTUBE = "youtube"
    MEDIUM = "medium"
    SUBSTACK = "substack"
    STACK_OVERFLOW = "stackoverflow"
    DEV_TO = "dev_to"
    HACKER_NEWS = "hackernews"
    WORDPRESS = "wordpress"
    TIKTOK = "tiktok"
    TELEGRAM = "telegram"
    DISCORD = "discord"


class SearchPriority(str, Enum):
    """Search priority levels"""
    HIGH = "high"
    MEDIUM = "medium"
    LOW = "low"
    ALL = "all"


class AliasSearchRequest(BaseModel):
    """Request model for alias search"""
    username: str = Field(..., description="Username/alias to search for", min_length=1, max_length=100)
    platforms: Optional[List[SocialPlatform]] = Field(
        None, 
        description="Specific platforms to search (if not provided, searches all)"
    )
    priority: SearchPriority = Field(
        SearchPriority.ALL, 
        description="Search priority level (high, medium, low, all)"
    )
    max_results_per_platform: int = Field(
        default=5, 
        description="Maximum results per platform", 
        ge=1, 
        le=10
    )
    include_variations: bool = Field(
        default=True, 
        description="Include username variations (with @, without @, etc.)"
    )


class PlatformResult(BaseModel):
    """Result from a specific platform"""
    platform: SocialPlatform = Field(..., description="Social media platform")
    query_used: str = Field(..., description="Google dork query used for this platform")
    total_results: int = Field(..., description="Total results found")
    search_time: float = Field(..., description="Search time in seconds")
    results: List[Dict[str, Any]] = Field(..., description="Search results")
    error: Optional[str] = Field(None, description="Error message if search failed")


class AliasSearchSummary(BaseModel):
    """Summary of alias search results"""
    username: str = Field(..., description="Username that was searched")
    total_platforms_searched: int = Field(..., description="Number of platforms searched")
    successful_searches: int = Field(..., description="Number of successful searches")
    failed_searches: int = Field(..., description="Number of failed searches")
    total_results_found: int = Field(..., description="Total results across all platforms")
    search_duration: float = Field(..., description="Total search duration in seconds")
    platforms_with_results: List[SocialPlatform] = Field(..., description="Platforms that returned results")


class AliasSearchResult(BaseModel):
    """Complete alias search response"""
    summary: AliasSearchSummary = Field(..., description="Search summary")
    platform_results: Dict[str, PlatformResult] = Field(..., description="Results by platform")
    recommendations: List[str] = Field(..., description="Recommendations for further investigation")


class AliasSearchError(BaseModel):
    """Error model for alias search failures"""
    error_type: str = Field(..., description="Type of error")
    message: str = Field(..., description="Error message")
    platform: Optional[SocialPlatform] = Field(None, description="Platform where error occurred")
    query: Optional[str] = Field(None, description="Query that caused the error")


class BulkAliasSearchRequest(BaseModel):
    """Request for searching multiple aliases"""
    usernames: List[str] = Field(
        ..., 
        description="List of usernames to search", 
        min_items=1, 
        max_items=10
    )
    platforms: Optional[List[SocialPlatform]] = Field(
        None, 
        description="Specific platforms to search"
    )
    priority: SearchPriority = Field(
        SearchPriority.HIGH, 
        description="Search priority level"
    )
    max_results_per_platform: int = Field(
        default=3, 
        description="Maximum results per platform per username", 
        ge=1, 
        le=5
    )


class BulkAliasSearchResult(BaseModel):
    """Result for bulk alias search"""
    total_usernames: int = Field(..., description="Total usernames searched")
    successful_searches: int = Field(..., description="Successful username searches")
    failed_searches: int = Field(..., description="Failed username searches")
    results: Dict[str, AliasSearchResult] = Field(..., description="Results by username")
    global_summary: Dict[str, Any] = Field(..., description="Global search statistics")