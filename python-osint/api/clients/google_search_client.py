"""
Google Custom Search API Client
Provides search capabilities using Google's Custom Search JSON API
"""

import asyncio
import logging
from typing import Dict, Any, List, Optional, Union
from datetime import datetime, timedelta
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

logger = logging.getLogger(__name__)


class GoogleSearchAPIError(Exception):
    """Custom exception for Google Search API errors"""
    def __init__(self, message: str, status_code: int = None, response: Dict = None):
        self.message = message
        self.status_code = status_code
        self.response = response
        super().__init__(self.message)


class GoogleSearchRateLimit:
    """Rate limiter for Google Custom Search API"""
    def __init__(self, calls_per_day: int = 100):
        self.calls_per_day = calls_per_day
        self.calls_today = []
    
    async def wait_if_needed(self):
        """Wait if rate limit would be exceeded"""
        now = datetime.now()
        today = now.date()
        
        # Remove calls from previous days
        self.calls_today = [call_time for call_time in self.calls_today 
                           if call_time.date() == today]
        
        if len(self.calls_today) >= self.calls_per_day:
            # Calculate wait time until tomorrow
            tomorrow = datetime.combine(today + timedelta(days=1), datetime.min.time())
            wait_seconds = (tomorrow - now).total_seconds()
            
            logger.warning(f"Rate limit reached. Waiting {wait_seconds:.0f} seconds until tomorrow")
            await asyncio.sleep(wait_seconds)
            self.calls_today = []
        
        self.calls_today.append(now)


class GoogleSearchClient:
    """
    Google Custom Search API client with rate limiting and error handling
    
    Provides search capabilities using Google's Custom Search JSON API.
    Requires a Google API key and Custom Search Engine ID.
    """
    
    def __init__(self, api_key: str, cse_id: str, rate_limit: GoogleSearchRateLimit = None):
        """
        Initialize Google Search client
        
        Args:
            api_key: Google API key
            cse_id: Custom Search Engine ID
            rate_limit: Rate limiter instance (optional)
        """
        self.api_key = api_key
        self.cse_id = cse_id
        self.rate_limit = rate_limit or GoogleSearchRateLimit()
        self._service = None
    
    def _get_service(self):
        """Get or create Google API service"""
        if not self._service:
            try:
                self._service = build('customsearch', 'v1', developerKey=self.api_key)
            except Exception as e:
                raise GoogleSearchAPIError(f"Failed to initialize Google API service: {e}")
        return self._service
    
    async def _wait_for_rate_limit(self):
        """Wait if necessary to respect rate limits"""
        await self.rate_limit.wait_if_needed()
    
    async def search(
        self, 
        query: str, 
        num_results: int = 10,
        start_index: int = 1,
        site_search: str = None,
        file_type: str = None,
        date_restrict: str = None,
        exact_terms: str = None,
        exclude_terms: str = None,
        link_site: str = None,
        or_terms: str = None,
        related_site: str = None,
        rights: str = None,
        safe: str = "medium",
        search_type: str = None
    ) -> Dict[str, Any]:
        """
        Perform a Google Custom Search
        
        Args:
            query: Search query string
            num_results: Number of results to return (1-10)
            start_index: Starting index for results (1-based)
            site_search: Restrict search to specific site
            file_type: File type filter (e.g., 'pdf', 'doc')
            date_restrict: Date restriction (e.g., 'd1', 'w1', 'm1', 'y1')
            exact_terms: Terms that must appear exactly
            exclude_terms: Terms to exclude from results
            link_site: Find pages linking to this site
            or_terms: Terms where any one should match
            related_site: Find pages related to this site
            rights: Copyright/usage rights filter
            safe: Safe search level ('active', 'medium', 'off')
            search_type: Search type ('image' for image search)
        
        Returns:
            Search results dictionary
        """
        await self._wait_for_rate_limit()
        
        service = self._get_service()
        
        # Build search parameters
        search_params = {
            'q': query,
            'cx': self.cse_id,
            'num': min(num_results, 10),  # API max is 10
            'start': start_index,
            'safe': safe
        }
        
        # Add optional parameters
        if site_search:
            search_params['siteSearch'] = site_search
        if file_type:
            search_params['fileType'] = file_type
        if date_restrict:
            search_params['dateRestrict'] = date_restrict
        if exact_terms:
            search_params['exactTerms'] = exact_terms
        if exclude_terms:
            search_params['excludeTerms'] = exclude_terms
        if link_site:
            search_params['linkSite'] = link_site
        if or_terms:
            search_params['orTerms'] = or_terms
        if related_site:
            search_params['relatedSite'] = related_site
        if rights:
            search_params['rights'] = rights
        if search_type:
            search_params['searchType'] = search_type
        
        try:
            logger.info(f"Performing Google search for: {query}")
            
            # Execute search (synchronous call wrapped in async)
            loop = asyncio.get_event_loop()
            result = await loop.run_in_executor(
                None, 
                lambda: service.cse().list(**search_params).execute()
            )
            
            logger.info(f"Search completed. Found {len(result.get('items', []))} results")
            return result
            
        except HttpError as e:
            error_details = e.error_details[0] if e.error_details else {}
            error_message = error_details.get('message', str(e))
            
            logger.error(f"Google Search API error: {error_message}")
            raise GoogleSearchAPIError(
                message=error_message,
                status_code=e.resp.status,
                response=error_details
            )
        except Exception as e:
            logger.error(f"Unexpected error during Google search: {e}")
            raise GoogleSearchAPIError(f"Search failed: {e}")
    
    async def image_search(
        self,
        query: str,
        num_results: int = 10,
        start_index: int = 1,
        image_size: str = None,
        image_type: str = None,
        image_color_type: str = None,
        image_dominant_color: str = None,
        safe: str = "medium"
    ) -> Dict[str, Any]:
        """
        Perform Google Image Search
        
        Args:
            query: Search query string
            num_results: Number of results to return (1-10)
            start_index: Starting index for results
            image_size: Image size ('icon', 'small', 'medium', 'large', 'xlarge', 'xxlarge', 'huge')
            image_type: Image type ('clipart', 'face', 'lineart', 'stock', 'photo', 'animated')
            image_color_type: Color type ('color', 'gray', 'mono', 'trans')
            image_dominant_color: Dominant color ('black', 'blue', 'brown', 'gray', 'green', 'orange', 'pink', 'purple', 'red', 'teal', 'white', 'yellow')
            safe: Safe search level
        
        Returns:
            Image search results dictionary
        """
        search_params = {}
        
        if image_size:
            search_params['imgSize'] = image_size
        if image_type:
            search_params['imgType'] = image_type
        if image_color_type:
            search_params['imgColorType'] = image_color_type
        if image_dominant_color:
            search_params['imgDominantColor'] = image_dominant_color
        
        return await self.search(
            query=query,
            num_results=num_results,
            start_index=start_index,
            safe=safe,
            search_type="image",
            **search_params
        )
    
    async def site_search(
        self,
        query: str,
        site: str,
        num_results: int = 10,
        start_index: int = 1
    ) -> Dict[str, Any]:
        """
        Search within a specific site
        
        Args:
            query: Search query
            site: Site domain to search within
            num_results: Number of results
            start_index: Starting index
        
        Returns:
            Site-specific search results
        """
        return await self.search(
            query=query,
            site_search=site,
            num_results=num_results,
            start_index=start_index
        )
    
    async def get_search_info(self) -> Dict[str, Any]:
        """
        Get information about the Custom Search Engine
        
        Returns:
            CSE information and statistics
        """
        await self._wait_for_rate_limit()
        
        try:
            # Perform a minimal search to get CSE info
            result = await self.search("test", num_results=1)
            
            search_info = result.get('searchInformation', {})
            context = result.get('context', {})
            
            return {
                'cse_id': self.cse_id,
                'title': context.get('title', 'Unknown'),
                'search_time': search_info.get('searchTime', 0),
                'formatted_search_time': search_info.get('formattedSearchTime', '0'),
                'total_results': search_info.get('totalResults', '0'),
                'formatted_total_results': search_info.get('formattedTotalResults', '0')
            }
            
        except Exception as e:
            logger.error(f"Failed to get search info: {e}")
            raise GoogleSearchAPIError(f"Failed to get search info: {e}")