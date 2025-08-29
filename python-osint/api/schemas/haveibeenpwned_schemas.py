"""
Pydantic schemas for HaveIBeenPwned API integration
"""

from typing import List, Optional, Dict, Any
from pydantic import BaseModel, EmailStr, Field, validator
import re


class BreachedAccountRequest(BaseModel):
    """Request schema for checking breached accounts"""
    email: EmailStr
    truncate_response: bool = Field(default=False, description="Return minimal breach information")
    domain_filter: Optional[str] = Field(default=None, description="Filter breaches by domain")
    include_unverified: bool = Field(default=False, description="Include unverified breaches")

    @validator('domain_filter')
    def validate_domain(cls, v):
        if v is not None:
            domain_pattern = r'^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$'
            if not re.match(domain_pattern, v):
                raise ValueError('Invalid domain format')
        return v


class BreachedDomainRequest(BaseModel):
    """Request schema for checking breached domains"""
    domain: str = Field(..., description="Domain to check for breaches")

    @validator('domain')
    def validate_domain(cls, v):
        domain_pattern = r'^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$'
        if not re.match(domain_pattern, v):
            raise ValueError('Invalid domain format')
        return v


class PwnedPasswordRequest(BaseModel):
    """Request schema for checking pwned passwords"""
    password: str = Field(..., description="Password to check", min_length=1)
    add_padding: bool = Field(default=True, description="Add padding for enhanced privacy")

    class Config:
        # Don't include password in logs or responses
        json_schema_extra = {
            "properties": {
                "password": {"writeOnly": True}
            }
        }


class PwnedPasswordHashRequest(BaseModel):
    """Request schema for checking pwned password hashes"""
    password_hash: str = Field(..., description="Password hash to check")
    hash_type: str = Field(default="sha1", description="Hash type (sha1 or ntlm)")

    @validator('hash_type')
    def validate_hash_type(cls, v):
        if v.lower() not in ['sha1', 'ntlm']:
            raise ValueError('hash_type must be "sha1" or "ntlm"')
        return v.lower()

    @validator('password_hash')
    def validate_hash_format(cls, v):
        if not re.match(r'^[a-fA-F0-9]+$', v):
            raise ValueError('Invalid hash format - must be hexadecimal')
        return v.upper()


class AllBreachesRequest(BaseModel):
    """Request schema for getting all breaches"""
    domain_filter: Optional[str] = Field(default=None, description="Filter breaches by domain")

    @validator('domain_filter')
    def validate_domain(cls, v):
        if v is not None:
            domain_pattern = r'^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$'
            if not re.match(domain_pattern, v):
                raise ValueError('Invalid domain format')
        return v


class BreachByNameRequest(BaseModel):
    """Request schema for getting breach by name"""
    breach_name: str = Field(..., description="Name of the breach to retrieve")

    @validator('breach_name')
    def validate_breach_name(cls, v):
        # Basic validation for breach name
        if not re.match(r'^[a-zA-Z0-9\-_\.]+$', v):
            raise ValueError('Invalid breach name format')
        return v


# Response schemas
class BreachResponse(BaseModel):
    """Response schema for breach information"""
    name: str
    title: str
    domain: str
    breach_date: str
    added_date: str
    modified_date: str
    pwn_count: int
    description: str
    logo_path: str
    data_classes: List[str]
    is_verified: bool
    is_fabricated: bool
    is_sensitive: bool
    is_retired: bool
    is_spam_list: bool
    is_malware: bool
    is_subscription_free: bool


class PwnedPasswordResponse(BaseModel):
    """Response schema for pwned password information"""
    is_pwned: bool = Field(..., description="Whether the password has been pwned")
    pwn_count: int = Field(..., description="Number of times the password appears in breaches")
    hash_suffix: str = Field(..., description="Last 35 characters of the hash (for reference)")
    risk_level: str = Field(..., description="Risk assessment based on pwn count")

    @validator('risk_level', always=True)
    def determine_risk_level(cls, v, values):
        pwn_count = values.get('pwn_count', 0)
        if pwn_count == 0:
            return 'safe'
        elif pwn_count < 10:
            return 'low'
        elif pwn_count < 100:
            return 'medium'
        elif pwn_count < 1000:
            return 'high'
        else:
            return 'critical'


class BreachedDomainResponse(BaseModel):
    """Response schema for breached domain information"""
    email: str
    breaches: List[str]


class BreachedAccountResponse(BaseModel):
    """Response schema for breached account check"""
    email: str
    is_breached: bool = Field(..., description="Whether the account has been breached")
    breach_count: int = Field(..., description="Number of breaches the account appears in")
    breaches: List[BreachResponse] = Field(..., description="Detailed breach information")
    data_classes_affected: List[str] = Field(..., description="Types of data compromised")
    most_recent_breach: Optional[str] = Field(default=None, description="Date of most recent breach")
    verified_breaches_count: int = Field(..., description="Number of verified breaches")
    unverified_breaches_count: int = Field(..., description="Number of unverified breaches")
    risk_assessment: str = Field(..., description="Overall risk assessment")

    @validator('risk_assessment', always=True)
    def determine_risk_assessment(cls, v, values):
        breach_count = values.get('breach_count', 0)
        verified_count = values.get('verified_breaches_count', 0)
        
        if breach_count == 0:
            return 'safe'
        elif verified_count == 0:
            return 'low'  # Only unverified breaches
        elif breach_count < 3:
            return 'medium'
        elif breach_count < 5:
            return 'high'
        else:
            return 'critical'


class HealthCheckResponse(BaseModel):
    """Response schema for health check"""
    status: str
    api_accessible: bool
    total_breaches: Optional[int] = None
    api_key_configured: bool
    timestamp: str
    error: Optional[str] = None


class HaveIBeenPwnedAnalysisResponse(BaseModel):
    """Complete analysis response combining all HIBP checks"""
    target: str
    target_type: str
    analysis_timestamp: str
    
    # Account breach information (for email targets)
    account_breaches: Optional[BreachedAccountResponse] = None
    
    # Domain breach information (for domain targets)
    domain_breaches: Optional[List[BreachedDomainResponse]] = None
    
    # Password analysis (for password targets)
    password_analysis: Optional[PwnedPasswordResponse] = None
    
    # Summary
    summary: Dict[str, Any] = Field(default_factory=dict)
    
    @validator('summary', always=True)
    def generate_summary(cls, v, values):
        summary = {
            'total_checks_performed': 0,
            'breaches_found': False,
            'password_compromised': False,
            'risk_level': 'safe',
            'recommendations': []
        }
        
        # Account breach analysis
        if values.get('account_breaches'):
            summary['total_checks_performed'] += 1
            account_data = values['account_breaches']
            if account_data.is_breached:
                summary['breaches_found'] = True
                summary['risk_level'] = account_data.risk_assessment
                summary['recommendations'].append('Change passwords for all accounts associated with this email')
                if account_data.breach_count > 3:
                    summary['recommendations'].append('Consider using a different email address for sensitive accounts')
        
        # Domain breach analysis
        if values.get('domain_breaches'):
            summary['total_checks_performed'] += 1
            if values['domain_breaches']:
                summary['breaches_found'] = True
                if summary['risk_level'] == 'safe':
                    summary['risk_level'] = 'medium'
                summary['recommendations'].append('Review security practices for this domain')
        
        # Password analysis
        if values.get('password_analysis'):
            summary['total_checks_performed'] += 1
            password_data = values['password_analysis']
            if password_data.is_pwned:
                summary['password_compromised'] = True
                summary['risk_level'] = password_data.risk_level
                summary['recommendations'].append('Change this password immediately')
                summary['recommendations'].append('Use a unique, strong password')
        
        return summary


# Bulk analysis schemas
class BulkEmailCheckRequest(BaseModel):
    """Request schema for bulk email checking"""
    emails: List[EmailStr] = Field(..., max_items=100, description="List of emails to check (max 100)")
    include_breach_details: bool = Field(default=True, description="Include detailed breach information")


class BulkPasswordCheckRequest(BaseModel):
    """Request schema for bulk password checking"""
    passwords: List[str] = Field(..., max_items=50, description="List of passwords to check (max 50)")
    add_padding: bool = Field(default=True, description="Add padding for enhanced privacy")

    class Config:
        # Don't include passwords in logs or responses
        json_schema_extra = {
            "properties": {
                "passwords": {"writeOnly": True}
            }
        }


class BulkAnalysisResponse(BaseModel):
    """Response schema for bulk analysis"""
    total_items: int
    items_processed: int
    items_failed: int
    analysis_results: List[HaveIBeenPwnedAnalysisResponse]
    failed_items: List[Dict[str, str]] = Field(default_factory=list)
    processing_time_seconds: float
    summary: Dict[str, Any]

    @validator('summary', always=True)
    def generate_bulk_summary(cls, v, values):
        results = values.get('analysis_results', [])
        
        summary = {
            'total_breached_accounts': sum(1 for r in results if r.account_breaches and r.account_breaches.is_breached),
            'total_compromised_passwords': sum(1 for r in results if r.password_analysis and r.password_analysis.is_pwned),
            'domains_with_breaches': sum(1 for r in results if r.domain_breaches and len(r.domain_breaches) > 0),
            'highest_risk_level': 'safe',
            'most_common_data_classes': [],
            'recommendations': set()
        }
        
        # Determine highest risk level
        risk_levels = ['safe', 'low', 'medium', 'high', 'critical']
        for result in results:
            risk = result.summary.get('risk_level', 'safe')
            if risk_levels.index(risk) > risk_levels.index(summary['highest_risk_level']):
                summary['highest_risk_level'] = risk
        
        # Collect recommendations
        for result in results:
            summary['recommendations'].update(result.summary.get('recommendations', []))
        
        summary['recommendations'] = list(summary['recommendations'])
        
        return summary