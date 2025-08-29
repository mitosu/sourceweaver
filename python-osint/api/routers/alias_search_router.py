"""
Alias Search Router
Specialized endpoint for searching usernames/aliases across social media platforms
"""

import asyncio
import logging
import time
from typing import Dict, Any, List, Optional
from fastapi import APIRouter, HTTPException, Depends, Query

from api.clients import GoogleSearchClient, GoogleSearchAPIError
from api.schemas import (
    SocialPlatform, SearchPriority,
    AliasSearchRequest, PlatformResult, AliasSearchSummary, AliasSearchResult,
    AliasSearchError, BulkAliasSearchRequest, BulkAliasSearchResult
)
from api.config import get_settings

logger = logging.getLogger(__name__)

# Create router
router = APIRouter(prefix="/alias-search", tags=["Alias & Social Media Search"])

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
    
    # Use configuration values for rate limiting
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
        pass


class SocialMediaQueryBuilder:
    """Builder for social media search queries"""
    
    @staticmethod
    def get_platform_queries(username: str, include_variations: bool = True) -> Dict[SocialPlatform, Dict[str, Any]]:
        """Get search queries for all supported platforms"""
        
        # Clean username variations
        clean_username = username.lstrip('@')
        at_username = f"@{clean_username}" if not username.startswith('@') else username
        
        # Build variations list
        variations = [f'"{clean_username}"']
        if include_variations:
            variations.extend([f'"{at_username}"', f'"{username}"'])
        
        variations_str = " OR ".join(set(variations))  # Remove duplicates
        
        return {
            # HIGH PRIORITY - Most popular platforms
            SocialPlatform.TWITTER_X: {
                "priority": "high",
                "query": f"site:x.com {variations_str} OR site:twitter.com {variations_str}",
                "description": "Twitter/X profile search"
            },
            SocialPlatform.LINKEDIN: {
                "priority": "high", 
                "query": f"site:linkedin.com {variations_str}",
                "description": "LinkedIn professional profile"
            },
            SocialPlatform.GITHUB: {
                "priority": "high",
                "query": f"site:github.com {variations_str}",
                "description": "GitHub repositories and profile"
            },
            SocialPlatform.INSTAGRAM: {
                "priority": "high",
                "query": f"site:instagram.com {variations_str}",
                "description": "Instagram profile"
            },
            SocialPlatform.FACEBOOK: {
                "priority": "high",
                "query": f"site:facebook.com {variations_str}",
                "description": "Facebook profile"
            },
            
            # MEDIUM PRIORITY - Content platforms
            SocialPlatform.YOUTUBE: {
                "priority": "medium",
                "query": f"site:youtube.com {variations_str}",
                "description": "YouTube channels and videos"
            },
            SocialPlatform.REDDIT: {
                "priority": "medium",
                "query": f"site:reddit.com {variations_str} OR site:reddit.com \"u/{clean_username}\"",
                "description": "Reddit user activity"
            },
            SocialPlatform.MEDIUM: {
                "priority": "medium",
                "query": f"site:medium.com {variations_str}",
                "description": "Medium articles and profile"
            },
            SocialPlatform.SUBSTACK: {
                "priority": "medium",
                "query": f"site:substack.com {variations_str}",
                "description": "Substack newsletters"
            },
            
            # MEDIUM PRIORITY - Technical platforms
            SocialPlatform.STACK_OVERFLOW: {
                "priority": "medium",
                "query": f"site:stackoverflow.com {variations_str}",
                "description": "Stack Overflow activity"
            },
            SocialPlatform.DEV_TO: {
                "priority": "medium", 
                "query": f"site:dev.to {variations_str}",
                "description": "Dev.to articles and profile"
            },
            SocialPlatform.HACKER_NEWS: {
                "priority": "medium",
                "query": f"site:news.ycombinator.com {variations_str}",
                "description": "Hacker News activity"
            },
            
            # LOW PRIORITY - Other platforms
            SocialPlatform.WORDPRESS: {
                "priority": "low",
                "query": f"site:wordpress.com {variations_str}",
                "description": "WordPress blogs"
            },
            SocialPlatform.TIKTOK: {
                "priority": "low",
                "query": f"site:tiktok.com {variations_str}",
                "description": "TikTok profile"
            },
            SocialPlatform.TELEGRAM: {
                "priority": "low",
                "query": f"\"{clean_username}\" telegram OR \"{at_username}\" telegram",
                "description": "Telegram mentions (limited search)"
            },
            SocialPlatform.DISCORD: {
                "priority": "low",
                "query": f"\"{clean_username}\" discord OR \"{at_username}\" discord",
                "description": "Discord mentions (limited search)"
            }
        }
    
    @staticmethod
    def filter_by_priority(platform_queries: Dict[SocialPlatform, Dict[str, Any]], 
                          priority: SearchPriority) -> Dict[SocialPlatform, Dict[str, Any]]:
        """Filter platforms by priority level"""
        if priority == SearchPriority.ALL:
            return platform_queries
        
        priority_map = {
            SearchPriority.HIGH: ["high"],
            SearchPriority.MEDIUM: ["high", "medium"], 
            SearchPriority.LOW: ["high", "medium", "low"]
        }
        
        allowed_priorities = priority_map.get(priority, ["high"])
        
        return {
            platform: config for platform, config in platform_queries.items()
            if config["priority"] in allowed_priorities
        }


async def search_platform(
    client: GoogleSearchClient,
    platform: SocialPlatform,
    query_config: Dict[str, Any],
    max_results: int
) -> PlatformResult:
    """Search a specific platform"""
    start_time = time.time()
    
    try:
        logger.info(f"Searching {platform.value} with query: {query_config['query']}")
        
        result = await client.search(
            query=query_config["query"],
            num_results=max_results
        )
        
        search_time = time.time() - start_time
        
        # Convert Google API response to our format
        items = result.get('items', [])
        search_info = result.get('searchInformation', {})
        
        return PlatformResult(
            platform=platform,
            query_used=query_config["query"],
            total_results=int(search_info.get('totalResults', '0')),
            search_time=search_time,
            results=[{
                'title': item.get('title', ''),
                'link': item.get('link', ''),
                'snippet': item.get('snippet', ''),
                'display_link': item.get('displayLink', ''),
                'formatted_url': item.get('formattedUrl', '')
            } for item in items]
        )
        
    except GoogleSearchAPIError as e:
        search_time = time.time() - start_time
        logger.error(f"Google Search API error for {platform.value}: {e.message}")
        
        return PlatformResult(
            platform=platform,
            query_used=query_config["query"],
            total_results=0,
            search_time=search_time,
            results=[],
            error=f"API Error: {e.message}"
        )
        
    except Exception as e:
        search_time = time.time() - start_time
        logger.error(f"Unexpected error searching {platform.value}: {e}")
        
        return PlatformResult(
            platform=platform,
            query_used=query_config["query"],
            total_results=0,
            search_time=search_time,
            results=[],
            error=f"Search Error: {str(e)}"
        )


def generate_recommendations(platform_results: Dict[str, PlatformResult], username: str) -> List[str]:
    """Generate investigation recommendations based on results"""
    recommendations = []
    
    platforms_with_results = [
        result.platform.value for result in platform_results.values() 
        if result.total_results > 0 and not result.error
    ]
    
    if len(platforms_with_results) == 0:
        recommendations.append(f"No direct results found for '{username}'. Try searching with variations or common misspellings.")
        recommendations.append("Consider manual verification on major platforms directly.")
    elif len(platforms_with_results) >= 5:
        recommendations.append(f"High online presence detected across {len(platforms_with_results)} platforms.")
        recommendations.append("Cross-reference profile information for identity verification.")
        recommendations.append("Check profile creation dates and posting patterns for authenticity.")
    else:
        recommendations.append(f"Moderate presence found on {len(platforms_with_results)} platforms.")
        recommendations.append("Consider expanding search to additional platforms or variations.")
    
    # Platform-specific recommendations
    if SocialPlatform.GITHUB.value in platforms_with_results:
        recommendations.append("GitHub profile found - analyze repositories and contribution patterns.")
    
    if SocialPlatform.LINKEDIN.value in platforms_with_results:
        recommendations.append("LinkedIn profile found - verify professional information and connections.")
    
    if SocialPlatform.TWITTER_X.value in platforms_with_results:
        recommendations.append("Twitter/X profile found - analyze tweet patterns and follower interactions.")
    
    recommendations.append("Always verify identity through multiple sources before drawing conclusions.")
    
    return recommendations


# Health check endpoint
@router.get("/health")
async def alias_search_health():
    """Health check for alias search service"""
    try:
        return {
            "status": "healthy",
            "service": "Alias Search API",
            "supported_platforms": [platform.value for platform in SocialPlatform],
            "features": ["single_search", "bulk_search", "priority_filtering", "platform_filtering"]
        }
    except Exception as e:
        raise HTTPException(status_code=503, detail=f"Service unavailable: {e}")


# Single alias search endpoint
@router.get("/search", response_model=AliasSearchResult)
async def search_alias(
    username: str = Query(..., description="Username/alias to search for"),
    platforms: Optional[str] = Query(None, description="Comma-separated platform names (optional)"),
    priority: SearchPriority = Query(SearchPriority.HIGH, description="Search priority level"),
    max_results: int = Query(5, ge=1, le=10, description="Max results per platform"),
    include_variations: bool = Query(True, description="Include username variations"),
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Search for a username/alias across social media platforms"""
    
    logger.info(f"Starting alias search for: {username}")
    start_time = time.time()
    
    try:
        # Parse platforms if specified
        target_platforms = None
        if platforms:
            try:
                platform_names = [p.strip().upper() for p in platforms.split(",")]
                target_platforms = [SocialPlatform(p.lower()) for p in platform_names if p]
            except ValueError as e:
                raise HTTPException(status_code=400, detail=f"Invalid platform name: {e}")
        
        # Get all platform queries
        query_builder = SocialMediaQueryBuilder()
        all_platform_queries = query_builder.get_platform_queries(username, include_variations)
        
        # Filter by priority
        filtered_queries = query_builder.filter_by_priority(all_platform_queries, priority)
        
        # Filter by specific platforms if requested
        if target_platforms:
            filtered_queries = {
                platform: config for platform, config in filtered_queries.items()
                if platform in target_platforms
            }
        
        if not filtered_queries:
            raise HTTPException(status_code=400, detail="No platforms selected for search")
        
        # Perform searches with rate limiting respect
        platform_results = {}
        successful_searches = 0
        failed_searches = 0
        total_results = 0
        
        for platform, query_config in filtered_queries.items():
            result = await search_platform(client, platform, query_config, max_results)
            platform_results[platform.value] = result
            
            if result.error:
                failed_searches += 1
            else:
                successful_searches += 1
                total_results += result.total_results
            
            # Small delay between platform searches to respect rate limits
            await asyncio.sleep(0.2)
        
        # Calculate total search time
        total_search_time = time.time() - start_time
        
        # Find platforms with results
        platforms_with_results = [
            SocialPlatform(result.platform.value) for result in platform_results.values()
            if result.total_results > 0 and not result.error
        ]
        
        # Create summary
        summary = AliasSearchSummary(
            username=username,
            total_platforms_searched=len(filtered_queries),
            successful_searches=successful_searches,
            failed_searches=failed_searches,
            total_results_found=total_results,
            search_duration=total_search_time,
            platforms_with_results=platforms_with_results
        )
        
        # Generate recommendations
        recommendations = generate_recommendations(platform_results, username)
        
        return AliasSearchResult(
            summary=summary,
            platform_results=platform_results,
            recommendations=recommendations
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Alias search failed for {username}: {e}")
        raise HTTPException(status_code=500, detail=f"Search failed: {str(e)}")


# POST endpoint for advanced search
@router.post("/search", response_model=AliasSearchResult)
async def search_alias_advanced(
    request: AliasSearchRequest,
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Advanced alias search with full request body"""
    
    logger.info(f"Starting advanced alias search for: {request.username}")
    
    # Convert to GET endpoint parameters and call the main search function
    platforms_str = ",".join([p.value for p in request.platforms]) if request.platforms else None
    
    return await search_alias(
        username=request.username,
        platforms=platforms_str,
        priority=request.priority,
        max_results=request.max_results_per_platform,
        include_variations=request.include_variations,
        client=client
    )


# Bulk alias search endpoint
@router.post("/bulk-search", response_model=BulkAliasSearchResult)
async def bulk_alias_search(
    request: BulkAliasSearchRequest,
    client: GoogleSearchClient = Depends(get_google_search_client)
):
    """Search multiple aliases in bulk"""
    
    logger.info(f"Starting bulk alias search for {len(request.usernames)} usernames")
    
    try:
        results = {}
        successful_searches = 0
        failed_searches = 0
        total_platforms_with_results = set()
        
        for username in request.usernames:
            try:
                platforms_str = ",".join([p.value for p in request.platforms]) if request.platforms else None
                
                result = await search_alias(
                    username=username,
                    platforms=platforms_str,
                    priority=request.priority,
                    max_results=request.max_results_per_platform,
                    include_variations=True,
                    client=client
                )
                
                results[username] = result
                successful_searches += 1
                total_platforms_with_results.update([p.value for p in result.summary.platforms_with_results])
                
                # Delay between bulk searches to respect rate limits
                await asyncio.sleep(1.0)
                
            except Exception as e:
                logger.error(f"Failed to search username {username}: {e}")
                failed_searches += 1
                # Create empty result for failed search
                results[username] = AliasSearchResult(
                    summary=AliasSearchSummary(
                        username=username,
                        total_platforms_searched=0,
                        successful_searches=0,
                        failed_searches=1,
                        total_results_found=0,
                        search_duration=0.0,
                        platforms_with_results=[]
                    ),
                    platform_results={},
                    recommendations=[f"Search failed for {username}: {str(e)}"]
                )
        
        # Global summary
        global_summary = {
            "total_usernames_processed": len(request.usernames),
            "successful_username_searches": successful_searches,
            "failed_username_searches": failed_searches,
            "unique_platforms_with_results": len(total_platforms_with_results),
            "platforms_found": list(total_platforms_with_results)
        }
        
        return BulkAliasSearchResult(
            total_usernames=len(request.usernames),
            successful_searches=successful_searches,
            failed_searches=failed_searches,
            results=results,
            global_summary=global_summary
        )
        
    except Exception as e:
        logger.error(f"Bulk alias search failed: {e}")
        raise HTTPException(status_code=500, detail=f"Bulk search failed: {str(e)}")


# Get supported platforms
@router.get("/platforms")
async def get_supported_platforms():
    """Get list of supported social media platforms"""
    return {
        "supported_platforms": [
            {
                "name": platform.value,
                "display_name": platform.value.replace("_", " ").title(),
                "description": f"Search profiles and content on {platform.value.replace('_', ' ').title()}"
            }
            for platform in SocialPlatform
        ],
        "priority_levels": {
            "high": "Twitter/X, LinkedIn, GitHub, Instagram, Facebook",
            "medium": "Adds YouTube, Reddit, Medium, Substack, Stack Overflow, Dev.to, Hacker News", 
            "low": "Adds WordPress, TikTok, Telegram, Discord",
            "all": "All supported platforms"
        }
    }