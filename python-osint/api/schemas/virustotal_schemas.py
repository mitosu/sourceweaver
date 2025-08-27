"""
VirusTotal API Schemas and Models
Pydantic models for request/response validation and serialization
"""

from typing import Dict, Any, List, Optional, Union
from datetime import datetime
from pydantic import BaseModel, Field, validator
from enum import Enum

class AnalysisType(str, Enum):
    """Types of analysis supported by VirusTotal"""
    FILE = "file"
    URL = "url"
    DOMAIN = "domain"
    IP = "ip"
    HASH = "hash"

class VerdictType(str, Enum):
    """Possible verdicts from VirusTotal analysis"""
    MALICIOUS = "malicious"
    SUSPICIOUS = "suspicious"
    UNDETECTED = "undetected"
    HARMLESS = "harmless"
    TIMEOUT = "timeout"
    CONFIRMED_TIMEOUT = "confirmed-timeout"
    FAILURE = "failure"
    TYPE_UNSUPPORTED = "type-unsupported"

# Request Models
class VirusTotalFileRequest(BaseModel):
    """Request model for file analysis"""
    password: Optional[str] = Field(None, description="Password for encrypted files")
    
class VirusTotalURLRequest(BaseModel):
    """Request model for URL analysis"""
    url: str = Field(..., description="URL to analyze", max_length=2048)
    
    @validator('url')
    def validate_url(cls, v):
        if not v.startswith(('http://', 'https://')):
            v = 'http://' + v
        return v

class VirusTotalHashRequest(BaseModel):
    """Request model for hash lookup"""
    hash: str = Field(..., description="File hash (MD5, SHA1, SHA256)", min_length=32)
    
class VirusTotalDomainRequest(BaseModel):
    """Request model for domain analysis"""
    domain: str = Field(..., description="Domain to analyze", max_length=253)

class VirusTotalIPRequest(BaseModel):
    """Request model for IP analysis"""
    ip_address: str = Field(..., description="IP address to analyze")

class VirusTotalSearchRequest(BaseModel):
    """Request model for VirusTotal search"""
    query: str = Field(..., description="Search query", max_length=1024)
    limit: int = Field(default=10, ge=1, le=300, description="Number of results to return")

# Response Models
class EngineResult(BaseModel):
    """Individual antivirus engine result"""
    category: str = Field(..., description="Detection category")
    engine_name: str = Field(..., description="Antivirus engine name")
    engine_version: str = Field(None, description="Engine version")
    result: Optional[str] = Field(None, description="Detection result")
    method: Optional[str] = Field(None, description="Detection method")
    engine_update: Optional[str] = Field(None, description="Engine update date")

class AnalysisStats(BaseModel):
    """Analysis statistics from VirusTotal"""
    harmless: int = Field(default=0, description="Number of harmless verdicts")
    malicious: int = Field(default=0, description="Number of malicious verdicts")
    suspicious: int = Field(default=0, description="Number of suspicious verdicts")
    timeout: int = Field(default=0, description="Number of timeout verdicts")
    undetected: int = Field(default=0, description="Number of undetected verdicts")
    confirmed_timeout: int = Field(default=0, description="Number of confirmed timeout verdicts")
    failure: int = Field(default=0, description="Number of failure verdicts")
    type_unsupported: int = Field(default=0, description="Number of type unsupported verdicts")
    
    @property
    def total_engines(self) -> int:
        """Total number of engines that analyzed the sample"""
        return sum([
            self.harmless, self.malicious, self.suspicious, 
            self.timeout, self.undetected, self.confirmed_timeout,
            self.failure, self.type_unsupported
        ])
    
    @property
    def detection_ratio(self) -> str:
        """Detection ratio as string (malicious/total)"""
        return f"{self.malicious}/{self.total_engines}"
    
    @property
    def is_malicious(self) -> bool:
        """Whether the sample is considered malicious"""
        return self.malicious > 0

class FileAttributes(BaseModel):
    """File analysis attributes from VirusTotal"""
    sha256: Optional[str] = None
    sha1: Optional[str] = None
    md5: Optional[str] = None
    size: Optional[int] = None
    type_description: Optional[str] = None
    type_tag: Optional[str] = None
    first_submission_date: Optional[int] = None
    last_analysis_date: Optional[int] = None
    last_analysis_stats: Optional[AnalysisStats] = None
    last_analysis_results: Optional[Dict[str, EngineResult]] = None
    magic: Optional[str] = None
    ssdeep: Optional[str] = None
    tlsh: Optional[str] = None
    names: Optional[List[str]] = None
    reputation: Optional[int] = None

class URLAttributes(BaseModel):
    """URL analysis attributes from VirusTotal"""
    url: Optional[str] = None
    last_analysis_date: Optional[int] = None
    last_analysis_stats: Optional[AnalysisStats] = None
    last_analysis_results: Optional[Dict[str, EngineResult]] = None
    first_submission_date: Optional[int] = None
    last_submission_date: Optional[int] = None
    reputation: Optional[int] = None
    times_submitted: Optional[int] = None
    title: Optional[str] = None
    categories: Optional[Dict[str, str]] = None

class DomainAttributes(BaseModel):
    """Domain analysis attributes from VirusTotal"""
    categories: Optional[Dict[str, str]] = None
    last_analysis_date: Optional[int] = None
    last_analysis_stats: Optional[AnalysisStats] = None
    last_analysis_results: Optional[Dict[str, EngineResult]] = None
    reputation: Optional[int] = None
    whois: Optional[str] = None
    whois_date: Optional[int] = None
    creation_date: Optional[int] = None
    registrar: Optional[str] = None
    last_dns_records: Optional[List[Dict[str, Any]]] = None
    last_dns_records_date: Optional[int] = None

class IPAttributes(BaseModel):
    """IP address analysis attributes from VirusTotal"""
    network: Optional[str] = None
    country: Optional[str] = None
    as_owner: Optional[str] = None
    asn: Optional[int] = None
    last_analysis_date: Optional[int] = None
    last_analysis_stats: Optional[AnalysisStats] = None
    last_analysis_results: Optional[Dict[str, EngineResult]] = None
    reputation: Optional[int] = None
    whois: Optional[str] = None
    whois_date: Optional[int] = None

class VirusTotalResource(BaseModel):
    """Generic VirusTotal resource"""
    type: str = Field(..., description="Resource type")
    id: str = Field(..., description="Resource identifier")
    attributes: Union[FileAttributes, URLAttributes, DomainAttributes, IPAttributes] = Field(..., description="Resource attributes")

class VirusTotalAnalysisResponse(BaseModel):
    """VirusTotal analysis response"""
    data: Optional[VirusTotalResource] = None
    error: Optional[Dict[str, Any]] = None

class VirusTotalSearchResult(BaseModel):
    """VirusTotal search result"""
    data: List[VirusTotalResource] = Field(default_factory=list)
    links: Optional[Dict[str, str]] = None
    meta: Optional[Dict[str, Any]] = None

class VirusTotalCommentsResponse(BaseModel):
    """VirusTotal comments response"""
    data: List[Dict[str, Any]] = Field(default_factory=list)

class VirusTotalSubdomainsResponse(BaseModel):
    """VirusTotal subdomains response"""
    data: List[Dict[str, Any]] = Field(default_factory=list)

class VirusTotalResolutionsResponse(BaseModel):
    """VirusTotal DNS resolutions response"""
    data: List[Dict[str, Any]] = Field(default_factory=list)

# Unified Response Models
class VirusTotalAnalysisResult(BaseModel):
    """Unified analysis result for different resource types"""
    resource_type: AnalysisType = Field(..., description="Type of resource analyzed")
    resource_id: str = Field(..., description="Resource identifier")
    status: str = Field(..., description="Analysis status")
    stats: Optional[AnalysisStats] = Field(None, description="Analysis statistics")
    engines: Optional[Dict[str, EngineResult]] = Field(None, description="Individual engine results")
    attributes: Optional[Dict[str, Any]] = Field(None, description="Additional attributes")
    analysis_date: Optional[datetime] = Field(None, description="Last analysis date")
    reputation: Optional[int] = Field(None, description="Resource reputation score")
    permalink: Optional[str] = Field(None, description="VirusTotal permalink")
    message: Optional[str] = Field(None, description="Status message")
    
    @validator('analysis_date', pre=True)
    def parse_analysis_date(cls, v):
        if isinstance(v, int):
            return datetime.fromtimestamp(v)
        return v

class VirusTotalBulkResult(BaseModel):
    """Bulk analysis result"""
    successful: List[VirusTotalAnalysisResult] = Field(default_factory=list)
    failed: List[Dict[str, Any]] = Field(default_factory=list)
    total_processed: int = Field(default=0)
    
class VirusTotalServiceInfo(BaseModel):
    """VirusTotal service information"""
    service_name: str = Field(default="VirusTotal")
    api_version: str = Field(default="v3")
    status: str = Field(..., description="Service status")
    rate_limit: Dict[str, Any] = Field(default_factory=dict)
    daily_quota_usage: Optional[float] = Field(None, description="Daily quota usage percentage")
    last_request_time: Optional[datetime] = None
    total_requests: int = Field(default=0)

# Error Models
class VirusTotalErrorResponse(BaseModel):
    """VirusTotal error response"""
    error: Dict[str, Any] = Field(..., description="Error information")
    
    @property
    def error_code(self) -> str:
        return self.error.get('code', 'UNKNOWN')
    
    @property 
    def error_message(self) -> str:
        return self.error.get('message', 'Unknown error')

# Configuration Models
class VirusTotalConfig(BaseModel):
    """VirusTotal configuration model"""
    api_key: str = Field(..., description="VirusTotal API key")
    rate_limit_per_minute: int = Field(default=4, description="Rate limit per minute")
    rate_limit_per_day: int = Field(default=500, description="Rate limit per day")
    timeout: int = Field(default=30, description="Request timeout in seconds")
    max_retries: int = Field(default=3, description="Maximum number of retries")
    enable_premium_features: bool = Field(default=False, description="Enable premium features")
    
    class Config:
        schema_extra = {
            "example": {
                "api_key": "your_virustotal_api_key_here",
                "rate_limit_per_minute": 4,
                "rate_limit_per_day": 500,
                "timeout": 30,
                "max_retries": 3,
                "enable_premium_features": False
            }
        }