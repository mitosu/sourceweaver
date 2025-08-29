"""
HaveIBeenPwned API Client
Integrates with HaveIBeenPwned API v3 for breach and password checking
"""

import os
import asyncio
import hashlib
import logging
from typing import List, Dict, Any, Optional, Union
from datetime import datetime, timedelta
from urllib.parse import quote

import aiohttp
from pydantic import BaseModel, Field


# Pydantic models for API responses
class BreachModel(BaseModel):
    """Model for breach information"""
    name: str = Field(alias="Name")
    title: Optional[str] = Field(None, alias="Title")
    domain: Optional[str] = Field(None, alias="Domain")
    breach_date: Optional[str] = Field(None, alias="BreachDate")
    added_date: Optional[str] = Field(None, alias="AddedDate")
    modified_date: Optional[str] = Field(None, alias="ModifiedDate")
    pwn_count: Optional[int] = Field(None, alias="PwnCount")
    description: Optional[str] = Field(None, alias="Description")
    logo_path: Optional[str] = Field(None, alias="LogoPath")
    data_classes: Optional[List[str]] = Field(None, alias="DataClasses")
    is_verified: Optional[bool] = Field(None, alias="IsVerified")
    is_fabricated: Optional[bool] = Field(None, alias="IsFabricated")
    is_sensitive: Optional[bool] = Field(None, alias="IsSensitive")
    is_retired: Optional[bool] = Field(None, alias="IsRetired")
    is_spam_list: Optional[bool] = Field(None, alias="IsSpamList")
    is_malware: Optional[bool] = Field(None, alias="IsMalware")
    is_subscription_free: Optional[bool] = Field(None, alias="IsSubscriptionFree")

    class Config:
        populate_by_name = True
        extra = "ignore"  # Ignore extra fields from the API


class PwnedPasswordModel(BaseModel):
    """Model for pwned password information"""
    hash_suffix: str
    count: int
    is_pwned: bool


class BreachedDomainModel(BaseModel):
    """Model for breached domain information"""
    email: str
    breaches: List[str]


class HaveIBeenPwnedClient:
    """
    Async HTTP client for HaveIBeenPwned API v3
    
    Provides methods for:
    - Checking breached accounts
    - Checking breached domains
    - Checking pwned passwords
    - Rate limiting and error handling
    """

    def __init__(self, api_key: Optional[str] = None):
        self.base_url = "https://haveibeenpwned.com/api/v3"
        self.pwned_passwords_url = "https://api.pwnedpasswords.com"
        self.session = None
        self.logger = logging.getLogger(__name__)
        
        self.api_key = api_key if api_key is not None else os.getenv("HAVEIBEENPWNED_API_KEY")
        
        # Rate limiting
        self.last_request_time = None
        self.min_request_interval = 1.5  # 1.5 seconds between requests (40 per minute max)
        
        # Headers
        self.headers = {
            "User-Agent": "SourceWeaver-OSINT-Tool/1.0 (Security Research)",
            "Accept": "application/json"
        }
        
        if self.api_key:
            self.headers["hibp-api-key"] = self.api_key

    async def __aenter__(self):
        """Async context manager entry"""
        self.session = aiohttp.ClientSession(headers=self.headers)
        return self

    async def __aexit__(self, exc_type, exc_val, exc_tb):
        """Async context manager exit"""
        if self.session:
            await self.session.close()

    async def _rate_limit(self):
        """Implement rate limiting to respect API limits"""
        if self.last_request_time:
            time_since_last = datetime.now() - self.last_request_time
            if time_since_last.total_seconds() < self.min_request_interval:
                wait_time = self.min_request_interval - time_since_last.total_seconds()
                await asyncio.sleep(wait_time)
        
        self.last_request_time = datetime.now()

    async def _make_request(self, url: str, params: Optional[Dict] = None) -> Union[List[Dict], Dict, str]:
        """
        Make HTTP request with rate limiting and error handling
        
        Args:
            url: Full URL to request
            params: Optional query parameters
            
        Returns:
            JSON response data
            
        Raises:
            Exception: For API errors
        """
        await self._rate_limit()
        
        if not self.session:
            raise Exception("Client session not initialized. Use async context manager.")
        
        try:
            async with self.session.get(url, params=params) as response:
                self.logger.info(f"HaveIBeenPwned API request: {url} - Status: {response.status}")
                
                if response.status == 200:
                    # Check content type
                    content_type = response.headers.get('content-type', '')
                    if 'application/json' in content_type:
                        return await response.json()
                    else:
                        return await response.text()
                
                elif response.status == 404:
                    # Not found - this is normal for accounts/domains with no breaches
                    return []
                
                elif response.status == 429:
                    # Rate limit exceeded
                    retry_after = response.headers.get('retry-after', '60')
                    raise Exception(f"Rate limit exceeded. Retry after {retry_after} seconds.")
                
                elif response.status == 401:
                    raise Exception("Unauthorized - Invalid API key")
                
                elif response.status == 403:
                    raise Exception("Forbidden - Missing or invalid User-Agent header")
                
                else:
                    error_text = await response.text()
                    raise Exception(f"API request failed with status {response.status}: {error_text}")
        
        except aiohttp.ClientError as e:
            raise Exception(f"Network error: {str(e)}")

    async def check_breached_account(self, 
                                   email: str, 
                                   truncate_response: bool = False,
                                   domain_filter: Optional[str] = None,
                                   include_unverified: bool = False) -> List[BreachModel]:
        """
        Check if an email account has been involved in any breaches
        
        Args:
            email: Email address to check
            truncate_response: If True, returns minimal breach info
            domain_filter: Only return breaches for specified domain
            include_unverified: Include unverified breaches
            
        Returns:
            List of breach information
        """
        if not self.api_key:
            raise Exception("API key required for breach checking")
        
        # URL encode the email address as required by the API
        encoded_email = quote(email, safe='')
        url = f"{self.base_url}/breachedaccount/{encoded_email}"
        
        params = {}
        if not truncate_response:
            params["truncateResponse"] = "false"
        if domain_filter:
            params["domain"] = domain_filter
        if include_unverified:
            params["includeUnverified"] = "true"
        
        try:
            response_data = await self._make_request(url, params)
            
            if not response_data:
                return []
            
            # Debug: Log the raw response data
            self.logger.warning(f"HIBP Raw response data: {response_data}")
            self.logger.warning(f"HIBP Response type: {type(response_data)}")
            if isinstance(response_data, list) and response_data:
                self.logger.warning(f"HIBP First breach keys: {response_data[0].keys() if isinstance(response_data[0], dict) else 'Not a dict'}")
            
            # Parse response into BreachModel objects
            breaches = []
            for breach_data in response_data:
                try:
                    breach = BreachModel(**breach_data)
                    breaches.append(breach)
                except Exception as e:
                    self.logger.warning(f"Failed to parse breach data: {e}")
                    self.logger.warning(f"Breach data that failed: {breach_data}")
                    # Add to breaches list anyway as raw data for debugging
                    continue
            
            return breaches
            
        except Exception as e:
            self.logger.error(f"Failed to check breached account {email}: {str(e)}")
            raise

    async def check_breached_domain(self, domain: str) -> List[BreachedDomainModel]:
        """
        Check if a domain has been involved in any breaches
        
        Args:
            domain: Domain to check (e.g., "example.com")
            
        Returns:
            List of breached email information for the domain
        """
        if not self.api_key:
            raise Exception("API key required for domain breach checking")
        
        # URL encode the domain as required by the API
        encoded_domain = quote(domain, safe='')
        url = f"{self.base_url}/breacheddomain/{encoded_domain}"
        
        try:
            response_data = await self._make_request(url)
            
            if not response_data:
                return []
            
            # Parse response - this endpoint returns different format
            breached_emails = []
            for email_data in response_data:
                try:
                    breached_email = BreachedDomainModel(**email_data)
                    breached_emails.append(breached_email)
                except Exception as e:
                    self.logger.warning(f"Failed to parse breached domain data: {e}")
                    continue
            
            return breached_emails
            
        except Exception as e:
            self.logger.error(f"Failed to check breached domain {domain}: {str(e)}")
            raise

    async def check_pwned_password(self, password: str, add_padding: bool = True) -> PwnedPasswordModel:
        """
        Check if a password has been pwned using k-Anonymity model
        
        Args:
            password: Password to check
            add_padding: Add padding for enhanced privacy
            
        Returns:
            Password pwn information
        """
        # Hash the password with SHA-1
        password_hash = hashlib.sha1(password.encode('utf-8')).hexdigest().upper()
        hash_prefix = password_hash[:5]
        hash_suffix = password_hash[5:]
        
        url = f"{self.pwned_passwords_url}/range/{hash_prefix}"
        
        params = {}
        if add_padding:
            params["Add-Padding"] = "true"
        
        try:
            response_data = await self._make_request(url, params)
            
            if isinstance(response_data, str):
                # Parse the response text
                lines = response_data.strip().split('\n')
                
                for line in lines:
                    if ':' in line:
                        suffix, count = line.split(':')
                        if suffix == hash_suffix:
                            return PwnedPasswordModel(
                                hash_suffix=hash_suffix,
                                count=int(count),
                                is_pwned=True
                            )
                
                # Password not found in breaches
                return PwnedPasswordModel(
                    hash_suffix=hash_suffix,
                    count=0,
                    is_pwned=False
                )
            
            else:
                raise Exception("Unexpected response format from Pwned Passwords API")
                
        except Exception as e:
            self.logger.error(f"Failed to check pwned password: {str(e)}")
            raise

    async def check_pwned_password_hash(self, password_hash: str, hash_type: str = "sha1") -> PwnedPasswordModel:
        """
        Check if a password hash has been pwned
        
        Args:
            password_hash: Password hash to check
            hash_type: Type of hash ("sha1" or "ntlm")
            
        Returns:
            Password pwn information
        """
        if hash_type.lower() not in ["sha1", "ntlm"]:
            raise ValueError("hash_type must be 'sha1' or 'ntlm'")
        
        password_hash = password_hash.upper()
        hash_prefix = password_hash[:5]
        hash_suffix = password_hash[5:]
        
        base_url = self.pwned_passwords_url
        if hash_type.lower() == "ntlm":
            base_url = f"{self.pwned_passwords_url}/ntlm"
        
        url = f"{base_url}/range/{hash_prefix}"
        
        try:
            response_data = await self._make_request(url)
            
            if isinstance(response_data, str):
                lines = response_data.strip().split('\n')
                
                for line in lines:
                    if ':' in line:
                        suffix, count = line.split(':')
                        if suffix == hash_suffix:
                            return PwnedPasswordModel(
                                hash_suffix=hash_suffix,
                                count=int(count),
                                is_pwned=True
                            )
                
                return PwnedPasswordModel(
                    hash_suffix=hash_suffix,
                    count=0,
                    is_pwned=False
                )
            
            else:
                raise Exception("Unexpected response format from Pwned Passwords API")
                
        except Exception as e:
            self.logger.error(f"Failed to check pwned password hash: {str(e)}")
            raise

    async def get_all_breaches(self, domain_filter: Optional[str] = None) -> List[BreachModel]:
        """
        Get information about all breaches in the system
        
        Args:
            domain_filter: Only return breaches for specified domain
            
        Returns:
            List of all breaches
        """
        url = f"{self.base_url}/breaches"
        
        params = {}
        if domain_filter:
            params["domain"] = domain_filter
        
        try:
            response_data = await self._make_request(url, params)
            
            if not response_data:
                return []
            
            breaches = []
            for breach_data in response_data:
                try:
                    breach = BreachModel(**breach_data)
                    breaches.append(breach)
                except Exception as e:
                    self.logger.warning(f"Failed to parse breach data: {e}")
                    continue
            
            return breaches
            
        except Exception as e:
            self.logger.error(f"Failed to get all breaches: {str(e)}")
            raise

    async def get_breach_by_name(self, breach_name: str) -> Optional[BreachModel]:
        """
        Get information about a specific breach by name
        
        Args:
            breach_name: Name of the breach
            
        Returns:
            Breach information or None if not found
        """
        url = f"{self.base_url}/breach/{breach_name}"
        
        try:
            response_data = await self._make_request(url)
            
            if not response_data:
                return None
            
            return BreachModel(**response_data)
            
        except Exception as e:
            if "404" in str(e):
                return None
            self.logger.error(f"Failed to get breach {breach_name}: {str(e)}")
            raise

    async def health_check(self) -> Dict[str, Any]:
        """
        Perform health check by making a simple API request
        
        Returns:
            Health status information
        """
        try:
            # Try to get all breaches - this doesn't require API key
            url = f"{self.base_url}/breaches"
            response_data = await self._make_request(url)
            
            breach_count = len(response_data) if response_data else 0
            
            return {
                "status": "healthy",
                "api_accessible": True,
                "total_breaches": breach_count,
                "api_key_configured": bool(self.api_key),
                "timestamp": datetime.now().isoformat()
            }
            
        except Exception as e:
            return {
                "status": "unhealthy",
                "api_accessible": False,
                "error": str(e),
                "api_key_configured": bool(self.api_key),
                "timestamp": datetime.now().isoformat()
            }