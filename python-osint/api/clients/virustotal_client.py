"""
VirusTotal API Client
Handles all interactions with VirusTotal's REST API v3
"""

import aiohttp
import asyncio
import logging
import time
from typing import Dict, Any, Optional, List, Union
from dataclasses import dataclass
from urllib.parse import quote

logger = logging.getLogger(__name__)

@dataclass
class VirusTotalRateLimit:
    """Rate limiting configuration for VirusTotal API"""
    requests_per_minute: int = 4  # Free tier limit
    requests_per_day: int = 500   # Free tier limit
    requests_per_month: int = 15500  # Free tier limit
    
class VirusTotalAPIError(Exception):
    """Custom exception for VirusTotal API errors"""
    def __init__(self, message: str, status_code: int = None, error_code: str = None):
        self.message = message
        self.status_code = status_code
        self.error_code = error_code
        super().__init__(self.message)

class VirusTotalClient:
    """
    Async client for VirusTotal API v3
    Handles authentication, rate limiting, and error handling
    """
    
    BASE_URL = "https://www.virustotal.com/api/v3"
    
    def __init__(self, api_key: str, rate_limit: VirusTotalRateLimit = None):
        self.api_key = api_key
        self.rate_limit = rate_limit or VirusTotalRateLimit()
        self.session: Optional[aiohttp.ClientSession] = None
        self.last_request_time = 0
        self.request_count = 0
        self.daily_request_count = 0
        self.last_reset_time = time.time()
        
    async def __aenter__(self):
        """Async context manager entry"""
        await self._create_session()
        return self
    
    async def __aexit__(self, exc_type, exc_val, exc_tb):
        """Async context manager exit"""
        await self._close_session()
    
    async def _create_session(self):
        """Create HTTP session with proper headers"""
        if not self.session:
            headers = {
                "x-apikey": self.api_key,
                "accept": "application/json",
                "content-type": "application/json"
            }
            timeout = aiohttp.ClientTimeout(total=30)
            self.session = aiohttp.ClientSession(
                headers=headers,
                timeout=timeout,
                connector=aiohttp.TCPConnector(limit=10)
            )
    
    async def _close_session(self):
        """Close HTTP session"""
        if self.session:
            await self.session.close()
            self.session = None
    
    async def _wait_for_rate_limit(self):
        """Implement rate limiting to avoid API quota violations"""
        current_time = time.time()
        
        # Reset daily counter if needed
        if current_time - self.last_reset_time > 86400:  # 24 hours
            self.daily_request_count = 0
            self.last_reset_time = current_time
        
        # Check daily limit
        if self.daily_request_count >= self.rate_limit.requests_per_day:
            raise VirusTotalAPIError(
                "Daily API request limit exceeded",
                status_code=429,
                error_code="DAILY_LIMIT_EXCEEDED"
            )
        
        # Implement per-minute rate limiting
        time_since_last_request = current_time - self.last_request_time
        min_interval = 60.0 / self.rate_limit.requests_per_minute
        
        if time_since_last_request < min_interval:
            wait_time = min_interval - time_since_last_request
            logger.info(f"Rate limiting: waiting {wait_time:.2f} seconds")
            await asyncio.sleep(wait_time)
        
        self.last_request_time = time.time()
        self.request_count += 1
        self.daily_request_count += 1
    
    async def _make_request(
        self, 
        method: str, 
        endpoint: str, 
        params: Dict[str, Any] = None,
        data: Dict[str, Any] = None,
        json_data: Dict[str, Any] = None
    ) -> Dict[str, Any]:
        """Make HTTP request to VirusTotal API with error handling"""
        await self._wait_for_rate_limit()
        await self._create_session()
        
        url = f"{self.BASE_URL}/{endpoint.lstrip('/')}"
        logger.info(f"Making VirusTotal request: {method} {url}")
        if json_data:
            logger.info(f"Request JSON data: {json_data}")
        if params:
            logger.info(f"Request params: {params}")
        if data:
            logger.info(f"Request data: {data}")
        
        try:
            async with self.session.request(
                method=method,
                url=url,
                params=params,
                data=data,
                json=json_data
            ) as response:
                response_data = await response.json()
                
                if response.status == 200:
                    return response_data
                elif response.status == 204:
                    return {"data": {}}
                elif response.status == 429:
                    raise VirusTotalAPIError(
                        "API rate limit exceeded",
                        status_code=429,
                        error_code="RATE_LIMIT_EXCEEDED"
                    )
                elif response.status == 401:
                    raise VirusTotalAPIError(
                        "Invalid API key",
                        status_code=401,
                        error_code="INVALID_API_KEY"
                    )
                elif response.status == 404:
                    raise VirusTotalAPIError(
                        "Resource not found",
                        status_code=404,
                        error_code="NOT_FOUND"
                    )
                else:
                    logger.error(f"VirusTotal API error - Status: {response.status}")
                    logger.error(f"VirusTotal API error - Response: {response_data}")
                    error_msg = response_data.get("error", {}).get("message", f"HTTP {response.status}")
                    raise VirusTotalAPIError(
                        error_msg,
                        status_code=response.status
                    )
                    
        except aiohttp.ClientError as e:
            raise VirusTotalAPIError(f"Network error: {str(e)}")
    
    async def _make_request_with_form(
        self, 
        method: str, 
        endpoint: str, 
        form_data: 'aiohttp.FormData' = None
    ) -> Dict[str, Any]:
        """Make HTTP request with FormData to VirusTotal API"""
        await self._wait_for_rate_limit()
        await self._create_session()
        
        url = f"{self.BASE_URL}/{endpoint.lstrip('/')}"
        logger.info(f"Making VirusTotal form request: {method} {url}")
        
        try:
            async with self.session.request(
                method=method,
                url=url,
                data=form_data
            ) as response:
                response_data = await response.json()
                
                if response.status == 200:
                    return response_data
                elif response.status == 204:
                    return {"data": {}}
                elif response.status == 429:
                    raise VirusTotalAPIError(
                        "API rate limit exceeded",
                        status_code=429,
                        error_code="RATE_LIMIT_EXCEEDED"
                    )
                elif response.status == 401:
                    raise VirusTotalAPIError(
                        "Invalid API key",
                        status_code=401,
                        error_code="INVALID_API_KEY"
                    )
                elif response.status == 404:
                    raise VirusTotalAPIError(
                        "Resource not found",
                        status_code=404,
                        error_code="NOT_FOUND"
                    )
                else:
                    logger.error(f"VirusTotal API error - Status: {response.status}")
                    logger.error(f"VirusTotal API error - Response: {response_data}")
                    error_msg = response_data.get("error", {}).get("message", f"HTTP {response.status}")
                    raise VirusTotalAPIError(
                        error_msg,
                        status_code=response.status
                    )
                    
        except aiohttp.ClientError as e:
            logger.error(f"VirusTotal client error: {e}")
            raise VirusTotalAPIError(f"Network error: {e}", status_code=0)
    
    # File Analysis Methods
    async def analyze_file(self, file_path: str) -> Dict[str, Any]:
        """Submit a file for analysis"""
        with open(file_path, 'rb') as f:
            form_data = aiohttp.FormData()
            form_data.add_field('file', f)
            
            return await self._make_request('POST', '/files', data=form_data)
    
    async def get_file_analysis(self, file_hash: str) -> Dict[str, Any]:
        """Get file analysis results by hash (MD5, SHA1, SHA256)"""
        return await self._make_request('GET', f'/files/{file_hash}')
    
    async def get_file_behaviours(self, file_hash: str) -> Dict[str, Any]:
        """Get file dynamic analysis (sandbox) results"""
        return await self._make_request('GET', f'/files/{file_hash}/behaviours')
    
    async def get_file_comments(self, file_hash: str, limit: int = 10) -> Dict[str, Any]:
        """Get comments for a file"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/files/{file_hash}/comments', params=params)
    
    async def add_file_comment(self, file_hash: str, comment: str) -> Dict[str, Any]:
        """Add a comment to a file"""
        json_data = {'data': {'type': 'comment', 'attributes': {'text': comment}}}
        return await self._make_request('POST', f'/files/{file_hash}/comments', json_data=json_data)
    
    # URL Analysis Methods
    async def analyze_url(self, url: str) -> Dict[str, Any]:
        """Submit a URL for analysis"""
        # VirusTotal URL endpoint expects form-encoded data, not JSON
        await self._wait_for_rate_limit()
        
        url_endpoint = f"{self.BASE_URL}/urls"
        form_data = {'url': url}
        
        logger.info(f"VirusTotal analyze_url called with: {url}")
        logger.info(f"Form data being sent: {form_data}")
        logger.info(f"Making direct form request to: {url_endpoint}")
        
        # Create a separate session without JSON content-type for form data
        headers = {
            "x-apikey": self.api_key,
            "accept": "application/json"
            # No content-type header - let aiohttp set it automatically for form data
        }
        
        timeout = aiohttp.ClientTimeout(total=30)
        async with aiohttp.ClientSession(headers=headers, timeout=timeout) as session:
            try:
                async with session.post(url_endpoint, data=form_data) as response:
                    response_data = await response.json()
                    
                    if response.status == 200:
                        return response_data
                    elif response.status == 204:
                        return {"data": {}}
                    elif response.status == 429:
                        raise VirusTotalAPIError(
                            "API rate limit exceeded",
                            status_code=429,
                            error_code="RATE_LIMIT_EXCEEDED"
                        )
                    elif response.status == 401:
                        raise VirusTotalAPIError(
                            "Invalid API key",
                            status_code=401,
                            error_code="INVALID_API_KEY"
                        )
                    else:
                        logger.error(f"VirusTotal API error - Status: {response.status}")
                        logger.error(f"VirusTotal API error - Response: {response_data}")
                        error_msg = response_data.get("error", {}).get("message", f"HTTP {response.status}")
                        raise VirusTotalAPIError(
                            error_msg,
                            status_code=response.status
                        )
            except aiohttp.ClientError as e:
                logger.error(f"VirusTotal client error: {e}")
                raise VirusTotalAPIError(f"Network error: {e}", status_code=0)
    
    async def get_url_analysis(self, url_id: str) -> Dict[str, Any]:
        """Get URL analysis results by URL ID or base64 encoded URL"""
        return await self._make_request('GET', f'/urls/{url_id}')
    
    async def get_url_comments(self, url_id: str, limit: int = 10) -> Dict[str, Any]:
        """Get comments for a URL"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/urls/{url_id}/comments', params=params)
    
    # Domain Analysis Methods
    async def get_domain_info(self, domain: str) -> Dict[str, Any]:
        """Get domain information and reputation"""
        return await self._make_request('GET', f'/domains/{domain}')
    
    async def get_domain_comments(self, domain: str, limit: int = 10) -> Dict[str, Any]:
        """Get comments for a domain"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/domains/{domain}/comments', params=params)
    
    async def get_domain_subdomains(self, domain: str, limit: int = 40) -> Dict[str, Any]:
        """Get subdomains for a domain"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/domains/{domain}/subdomains', params=params)
    
    async def get_domain_resolutions(self, domain: str, limit: int = 40) -> Dict[str, Any]:
        """Get DNS resolutions for a domain"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/domains/{domain}/resolutions', params=params)
    
    # IP Address Analysis Methods
    async def get_ip_info(self, ip_address: str) -> Dict[str, Any]:
        """Get IP address information and reputation"""
        return await self._make_request('GET', f'/ip_addresses/{ip_address}')
    
    async def get_ip_comments(self, ip_address: str, limit: int = 10) -> Dict[str, Any]:
        """Get comments for an IP address"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/ip_addresses/{ip_address}/comments', params=params)
    
    async def get_ip_resolutions(self, ip_address: str, limit: int = 40) -> Dict[str, Any]:
        """Get DNS resolutions for an IP address"""
        params = {'limit': limit}
        return await self._make_request('GET', f'/ip_addresses/{ip_address}/resolutions', params=params)
    
    # Search Methods
    async def search(self, query: str, limit: int = 300) -> Dict[str, Any]:
        """Search for files, URLs, domains, and IP addresses"""
        params = {'query': query, 'limit': limit}
        return await self._make_request('GET', '/search', params=params)
    
    # Analysis Methods
    async def get_analysis(self, analysis_id: str) -> Dict[str, Any]:
        """Get analysis results by analysis ID"""
        return await self._make_request('GET', f'/analyses/{analysis_id}')
    
    # Intelligence Methods
    async def get_intelligence_hunting_rulesets(self, limit: int = 10) -> Dict[str, Any]:
        """Get intelligence hunting rulesets (Premium feature)"""
        params = {'limit': limit}
        return await self._make_request('GET', '/intelligence/hunting_rulesets', params=params)
    
    # Utility Methods
    async def test_connection(self) -> bool:
        """Test API connection and authentication"""
        try:
            await self._make_request('GET', '/users/current')
            return True
        except VirusTotalAPIError:
            return False
    
    def get_stats(self) -> Dict[str, Any]:
        """Get client usage statistics"""
        return {
            "total_requests": self.request_count,
            "daily_requests": self.daily_request_count,
            "rate_limit": {
                "requests_per_minute": self.rate_limit.requests_per_minute,
                "requests_per_day": self.rate_limit.requests_per_day,
                "daily_usage_percentage": (self.daily_request_count / self.rate_limit.requests_per_day) * 100
            }
        }