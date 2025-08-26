"""
Pydantic models for OSINT Analysis API
"""

from typing import Dict, Any, Optional, List
from pydantic import BaseModel, Field, validator
from enum import Enum

class TargetType(str, Enum):
    IP = "ip"
    DOMAIN = "domain"
    URL = "url"
    EMAIL = "email"
    HASH = "hash"
    PHONE = "phone"

class AnalysisStatus(str, Enum):
    SUCCESS = "success"
    ERROR = "error"
    PROCESSING = "processing"
    PENDING = "pending"

class AnalysisRequest(BaseModel):
    target_type: TargetType = Field(..., description="Type of target to analyze")
    target_value: str = Field(..., min_length=1, description="Target value to analyze")
    config: Optional[Dict[str, Any]] = Field(default={}, description="Configuration parameters")
    async_analysis: bool = Field(default=False, description="Whether to run analysis asynchronously")
    timeout: int = Field(default=120, ge=10, le=600, description="Analysis timeout in seconds")
    
    @validator('target_value')
    def validate_target_value(cls, v, values):
        """Validate target value based on type"""
        if 'target_type' not in values:
            return v
            
        target_type = values['target_type']
        
        # Basic validation - more detailed validation in analysis scripts
        if target_type == TargetType.IP:
            import ipaddress
            try:
                ipaddress.ip_address(v)
            except ValueError:
                raise ValueError("Invalid IP address format")
        elif target_type == TargetType.EMAIL:
            if '@' not in v or '.' not in v.split('@')[-1]:
                raise ValueError("Invalid email format")
        elif target_type == TargetType.URL:
            if not v.startswith(('http://', 'https://')):
                raise ValueError("URL must start with http:// or https://")
        
        return v

class AnalysisResponse(BaseModel):
    status: AnalysisStatus = Field(..., description="Analysis status")
    data: Dict[str, Any] = Field(default={}, description="Analysis results")
    error: Optional[str] = Field(None, description="Error message if status is error")
    execution_time: float = Field(..., description="Analysis execution time in seconds")
    task_id: Optional[str] = Field(None, description="Task ID for async operations")
    timestamp: Optional[str] = Field(None, description="Analysis timestamp")

class ScriptInfo(BaseModel):
    name: str = Field(..., description="Script filename")
    target_type: TargetType = Field(..., description="Target type supported by script")
    description: str = Field(..., description="Script description")
    version: str = Field(default="1.0.0", description="Script version")
    author: str = Field(default="OSINT Team", description="Script author")
    supported_apis: List[str] = Field(default=[], description="APIs used by script")

class HealthResponse(BaseModel):
    status: str = Field(..., description="Overall health status")
    version: str = Field(..., description="API version")
    services: Dict[str, Any] = Field(..., description="Individual service health status")
    uptime: Optional[float] = Field(None, description="Service uptime in seconds")

class BulkAnalysisRequest(BaseModel):
    requests: List[AnalysisRequest] = Field(..., max_items=100, description="List of analysis requests")
    max_concurrent: int = Field(default=5, ge=1, le=20, description="Maximum concurrent analyses")

class ApiStats(BaseModel):
    total_requests: int = Field(..., description="Total API requests")
    successful_analyses: int = Field(..., description="Successful analyses")
    failed_analyses: int = Field(..., description="Failed analyses")
    average_response_time: float = Field(..., description="Average response time")
    uptime: float = Field(..., description="Service uptime")
    active_scripts: int = Field(..., description="Number of active scripts")
    
class ThreatScore(BaseModel):
    score: int = Field(..., ge=0, le=100, description="Threat score (0-100)")
    level: str = Field(..., description="Threat level (clean, low, medium, high)")
    factors: List[str] = Field(default=[], description="Factors contributing to score")
    confidence: float = Field(..., ge=0.0, le=1.0, description="Confidence in assessment")