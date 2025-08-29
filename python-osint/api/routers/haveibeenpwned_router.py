"""
FastAPI router for HaveIBeenPwned API integration
"""

import os
import logging
from typing import List, Optional
from datetime import datetime

from fastapi import APIRouter, HTTPException, Depends, Query, Path
from fastapi.responses import JSONResponse

from ..clients.haveibeenpwned_client import HaveIBeenPwnedClient, BreachModel, PwnedPasswordModel
from ..config import get_settings
from ..schemas.haveibeenpwned_schemas import (
    BreachedAccountRequest,
    BreachedDomainRequest,
    PwnedPasswordRequest,
    PwnedPasswordHashRequest,
    AllBreachesRequest,
    BreachByNameRequest,
    BulkEmailCheckRequest,
    BulkPasswordCheckRequest,
    BreachedAccountResponse,
    BreachedDomainResponse,
    PwnedPasswordResponse,
    BreachResponse,
    HealthCheckResponse,
    HaveIBeenPwnedAnalysisResponse,
    BulkAnalysisResponse
)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Create router
router = APIRouter(
    prefix="/api/v1/haveibeenpwned",
    tags=["HaveIBeenPwned"],
    responses={
        429: {"description": "Rate limit exceeded"},
        401: {"description": "Unauthorized - Invalid API key"},
        403: {"description": "Forbidden - Missing User-Agent"},
        404: {"description": "Not found"},
        500: {"description": "Internal server error"}
    }
)


async def get_hibp_client() -> HaveIBeenPwnedClient:
    """Dependency to create HaveIBeenPwned client"""
    settings = get_settings()
    return HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key)


@router.get("/health", response_model=HealthCheckResponse)
async def health_check():
    """
    Health check endpoint for HaveIBeenPwned API
    
    Returns:
        API health status and configuration information
    """
    logger.info("HaveIBeenPwned health check requested")
    
    try:
        settings = get_settings()
        # Log the API key status for debugging
        api_key_from_settings = settings.haveibeenpwned_api_key
        api_key_from_env = os.getenv("HAVEIBEENPWNED_API_KEY")
        
        logger.info(f"API key from settings: {'YES' if api_key_from_settings else 'NO'}")
        logger.info(f"API key from env: {'YES' if api_key_from_env else 'NO'}")
        
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            health_data = await client.health_check()
            return HealthCheckResponse(**health_data)
    
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        return HealthCheckResponse(
            status="unhealthy",
            api_accessible=False,
            error=str(e),
            api_key_configured=False,
            timestamp=datetime.now().isoformat()
        )


@router.get("/breaches", response_model=List[BreachResponse])
async def get_all_breaches(
    domain: Optional[str] = Query(None, description="Filter breaches by domain")
):
    """
    Get information about all breaches in the system
    
    Args:
        domain: Optional domain filter
        
    Returns:
        List of all breaches (or filtered by domain)
    """
    logger.info(f"Getting all breaches with domain filter: {domain}")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            breaches = await client.get_all_breaches(domain_filter=domain)
            
            response_breaches = []
            for breach in breaches:
                response_breaches.append(BreachResponse(
                    name=breach.name,
                    title=breach.title,
                    domain=breach.domain,
                    breach_date=breach.breach_date,
                    added_date=breach.added_date,
                    modified_date=breach.modified_date,
                    pwn_count=breach.pwn_count,
                    description=breach.description,
                    logo_path=breach.logo_path,
                    data_classes=breach.data_classes,
                    is_verified=breach.is_verified,
                    is_fabricated=breach.is_fabricated,
                    is_sensitive=breach.is_sensitive,
                    is_retired=breach.is_retired,
                    is_spam_list=breach.is_spam_list,
                    is_malware=breach.is_malware,
                    is_subscription_free=breach.is_subscription_free
                ))
            
            logger.info(f"Retrieved {len(response_breaches)} breaches")
            return response_breaches
    
    except Exception as e:
        logger.error(f"Failed to get all breaches: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        elif "Unauthorized" in str(e):
            raise HTTPException(status_code=401, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to retrieve breaches: {str(e)}")


@router.get("/breach/{breach_name}", response_model=BreachResponse)
async def get_breach_by_name(
    breach_name: str = Path(..., description="Name of the breach to retrieve")
):
    """
    Get information about a specific breach by name
    
    Args:
        breach_name: Name of the breach
        
    Returns:
        Detailed breach information
    """
    logger.info(f"Getting breach information for: {breach_name}")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            breach = await client.get_breach_by_name(breach_name)
            
            if not breach:
                raise HTTPException(status_code=404, detail=f"Breach '{breach_name}' not found")
            
            response = BreachResponse(
                name=breach.name,
                title=breach.title,
                domain=breach.domain,
                breach_date=breach.breach_date,
                added_date=breach.added_date,
                modified_date=breach.modified_date,
                pwn_count=breach.pwn_count,
                description=breach.description,
                logo_path=breach.logo_path,
                data_classes=breach.data_classes,
                is_verified=breach.is_verified,
                is_fabricated=breach.is_fabricated,
                is_sensitive=breach.is_sensitive,
                is_retired=breach.is_retired,
                is_spam_list=breach.is_spam_list,
                is_malware=breach.is_malware,
                is_subscription_free=breach.is_subscription_free
            )
            
            logger.info(f"Retrieved breach information for {breach_name}")
            return response
    
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Failed to get breach {breach_name}: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        elif "Unauthorized" in str(e):
            raise HTTPException(status_code=401, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to retrieve breach information: {str(e)}")


@router.get("/breachedaccount/{email}", response_model=BreachedAccountResponse)
async def check_breached_account(
    email: str = Path(..., description="Email address to check"),
    truncate_response: bool = Query(False, description="Return minimal breach information"),
    domain: Optional[str] = Query(None, description="Filter breaches by domain"),
    include_unverified: bool = Query(False, description="Include unverified breaches")
):
    """
    Check if an email account has been involved in any breaches
    
    Args:
        email: Email address to check
        truncate_response: Return minimal breach information
        domain: Filter breaches by specific domain
        include_unverified: Include unverified breaches
        
    Returns:
        Detailed breach information for the account
    """
    logger.info(f"Checking breached account for: {email}")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            breaches = await client.check_breached_account(
                email=email,
                truncate_response=truncate_response,
                domain_filter=domain,
                include_unverified=include_unverified
            )
            
            # Process the breaches
            breach_responses = []
            data_classes_affected = set()
            verified_count = 0
            unverified_count = 0
            most_recent_date = None
            
            for breach in breaches:
                breach_response = BreachResponse(
                    name=breach.name,
                    title=breach.title,
                    domain=breach.domain,
                    breach_date=breach.breach_date,
                    added_date=breach.added_date,
                    modified_date=breach.modified_date,
                    pwn_count=breach.pwn_count,
                    description=breach.description,
                    logo_path=breach.logo_path,
                    data_classes=breach.data_classes,
                    is_verified=breach.is_verified,
                    is_fabricated=breach.is_fabricated,
                    is_sensitive=breach.is_sensitive,
                    is_retired=breach.is_retired,
                    is_spam_list=breach.is_spam_list,
                    is_malware=breach.is_malware,
                    is_subscription_free=breach.is_subscription_free
                )
                breach_responses.append(breach_response)
                
                # Collect data classes
                data_classes_affected.update(breach.data_classes)
                
                # Count verified/unverified
                if breach.is_verified:
                    verified_count += 1
                else:
                    unverified_count += 1
                
                # Track most recent breach
                if not most_recent_date or breach.breach_date > most_recent_date:
                    most_recent_date = breach.breach_date
            
            response = BreachedAccountResponse(
                email=email,
                is_breached=len(breaches) > 0,
                breach_count=len(breaches),
                breaches=breach_responses,
                data_classes_affected=list(data_classes_affected),
                most_recent_breach=most_recent_date,
                verified_breaches_count=verified_count,
                unverified_breaches_count=unverified_count,
                risk_assessment=""  # Will be set by validator
            )
            
            logger.info(f"Found {len(breaches)} breaches for account {email}")
            return response
    
    except Exception as e:
        logger.error(f"Failed to check breached account {email}: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        elif "Unauthorized" in str(e):
            raise HTTPException(status_code=401, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to check account: {str(e)}")


@router.post("/breachedaccount", response_model=BreachedAccountResponse)
async def check_breached_account_post(request: BreachedAccountRequest):
    """
    Check if an email account has been involved in any breaches (POST version)
    
    Args:
        request: Breached account check request
        
    Returns:
        Detailed breach information for the account
    """
    return await check_breached_account(
        email=str(request.email),
        truncate_response=request.truncate_response,
        domain=request.domain_filter,
        include_unverified=request.include_unverified
    )


@router.get("/breacheddomain/{domain}", response_model=List[BreachedDomainResponse])
async def check_breached_domain(
    domain: str = Path(..., description="Domain to check for breaches")
):
    """
    Check if a domain has been involved in any breaches
    
    Args:
        domain: Domain to check
        
    Returns:
        List of breached emails for the domain
    """
    logger.info(f"Checking breached domain: {domain}")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            breached_emails = await client.check_breached_domain(domain)
            
            responses = []
            for breached_email in breached_emails:
                responses.append(BreachedDomainResponse(
                    email=breached_email.email,
                    breaches=breached_email.breaches
                ))
            
            logger.info(f"Found {len(responses)} breached emails for domain {domain}")
            return responses
    
    except Exception as e:
        logger.error(f"Failed to check breached domain {domain}: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        elif "Unauthorized" in str(e):
            raise HTTPException(status_code=401, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to check domain: {str(e)}")


@router.post("/breacheddomain", response_model=List[BreachedDomainResponse])
async def check_breached_domain_post(request: BreachedDomainRequest):
    """
    Check if a domain has been involved in any breaches (POST version)
    
    Args:
        request: Breached domain check request
        
    Returns:
        List of breached emails for the domain
    """
    return await check_breached_domain(domain=request.domain)


@router.post("/pwnedpassword", response_model=PwnedPasswordResponse)
async def check_pwned_password(request: PwnedPasswordRequest):
    """
    Check if a password has been pwned using k-Anonymity model
    
    Args:
        request: Password check request (password field is write-only)
        
    Returns:
        Password compromise information
    """
    logger.info("Checking pwned password (password not logged for security)")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            result = await client.check_pwned_password(
                password=request.password,
                add_padding=request.add_padding
            )
            
            response = PwnedPasswordResponse(
                is_pwned=result.is_pwned,
                pwn_count=result.count,
                hash_suffix=result.hash_suffix,
                risk_level=""  # Will be set by validator
            )
            
            logger.info(f"Password check completed - Pwned: {result.is_pwned}, Count: {result.count}")
            return response
    
    except Exception as e:
        logger.error(f"Failed to check pwned password: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to check password: {str(e)}")


@router.post("/pwnedpassword/hash", response_model=PwnedPasswordResponse)
async def check_pwned_password_hash(request: PwnedPasswordHashRequest):
    """
    Check if a password hash has been pwned
    
    Args:
        request: Password hash check request
        
    Returns:
        Password compromise information
    """
    logger.info(f"Checking pwned password hash ({request.hash_type})")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            result = await client.check_pwned_password_hash(
                password_hash=request.password_hash,
                hash_type=request.hash_type
            )
            
            response = PwnedPasswordResponse(
                is_pwned=result.is_pwned,
                pwn_count=result.count,
                hash_suffix=result.hash_suffix,
                risk_level=""  # Will be set by validator
            )
            
            logger.info(f"Hash check completed - Pwned: {result.is_pwned}, Count: {result.count}")
            return response
    
    except Exception as e:
        logger.error(f"Failed to check pwned password hash: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to check password hash: {str(e)}")


@router.post("/analyze/email", response_model=HaveIBeenPwnedAnalysisResponse)
async def analyze_email(request: BreachedAccountRequest):
    """
    Complete email analysis using HaveIBeenPwned services
    
    Args:
        request: Email analysis request
        
    Returns:
        Complete analysis results
    """
    logger.info(f"Performing complete email analysis for: {request.email}")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            # Check breached account
            breaches = await client.check_breached_account(
                email=str(request.email),
                truncate_response=request.truncate_response,
                domain_filter=request.domain_filter,
                include_unverified=request.include_unverified
            )
            
            # Process breaches into response format
            breach_responses = []
            data_classes_affected = set()
            verified_count = 0
            unverified_count = 0
            most_recent_date = None
            
            for breach in breaches:
                breach_response = BreachResponse(
                    name=breach.name,
                    title=breach.title,
                    domain=breach.domain,
                    breach_date=breach.breach_date,
                    added_date=breach.added_date,
                    modified_date=breach.modified_date,
                    pwn_count=breach.pwn_count,
                    description=breach.description,
                    logo_path=breach.logo_path,
                    data_classes=breach.data_classes,
                    is_verified=breach.is_verified,
                    is_fabricated=breach.is_fabricated,
                    is_sensitive=breach.is_sensitive,
                    is_retired=breach.is_retired,
                    is_spam_list=breach.is_spam_list,
                    is_malware=breach.is_malware,
                    is_subscription_free=breach.is_subscription_free
                )
                breach_responses.append(breach_response)
                data_classes_affected.update(breach.data_classes)
                
                if breach.is_verified:
                    verified_count += 1
                else:
                    unverified_count += 1
                
                if not most_recent_date or breach.breach_date > most_recent_date:
                    most_recent_date = breach.breach_date
            
            account_breaches = BreachedAccountResponse(
                email=str(request.email),
                is_breached=len(breaches) > 0,
                breach_count=len(breaches),
                breaches=breach_responses,
                data_classes_affected=list(data_classes_affected),
                most_recent_breach=most_recent_date,
                verified_breaches_count=verified_count,
                unverified_breaches_count=unverified_count,
                risk_assessment=""  # Will be set by validator
            )
            
            response = HaveIBeenPwnedAnalysisResponse(
                target=str(request.email),
                target_type="email",
                analysis_timestamp=datetime.now().isoformat(),
                account_breaches=account_breaches
            )
            
            logger.info(f"Email analysis completed for {request.email}")
            return response
    
    except Exception as e:
        logger.error(f"Failed to analyze email {request.email}: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        elif "Unauthorized" in str(e):
            raise HTTPException(status_code=401, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to analyze email: {str(e)}")


@router.post("/analyze/domain", response_model=HaveIBeenPwnedAnalysisResponse)
async def analyze_domain(request: BreachedDomainRequest):
    """
    Complete domain analysis using HaveIBeenPwned services
    
    Args:
        request: Domain analysis request
        
    Returns:
        Complete analysis results
    """
    logger.info(f"Performing complete domain analysis for: {request.domain}")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            # Check breached domain
            breached_emails = await client.check_breached_domain(request.domain)
            
            domain_responses = []
            for breached_email in breached_emails:
                domain_responses.append(BreachedDomainResponse(
                    email=breached_email.email,
                    breaches=breached_email.breaches
                ))
            
            response = HaveIBeenPwnedAnalysisResponse(
                target=request.domain,
                target_type="domain",
                analysis_timestamp=datetime.now().isoformat(),
                domain_breaches=domain_responses
            )
            
            logger.info(f"Domain analysis completed for {request.domain}")
            return response
    
    except Exception as e:
        logger.error(f"Failed to analyze domain {request.domain}: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        elif "Unauthorized" in str(e):
            raise HTTPException(status_code=401, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to analyze domain: {str(e)}")


@router.post("/analyze/password", response_model=HaveIBeenPwnedAnalysisResponse)
async def analyze_password(request: PwnedPasswordRequest):
    """
    Complete password analysis using HaveIBeenPwned services
    
    Args:
        request: Password analysis request
        
    Returns:
        Complete analysis results
    """
    logger.info("Performing complete password analysis (password not logged)")
    
    try:
        settings = get_settings()
        async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
            # Check pwned password
            result = await client.check_pwned_password(
                password=request.password,
                add_padding=request.add_padding
            )
            
            password_analysis = PwnedPasswordResponse(
                is_pwned=result.is_pwned,
                pwn_count=result.count,
                hash_suffix=result.hash_suffix,
                risk_level=""  # Will be set by validator
            )
            
            response = HaveIBeenPwnedAnalysisResponse(
                target="[PASSWORD HIDDEN]",
                target_type="password",
                analysis_timestamp=datetime.now().isoformat(),
                password_analysis=password_analysis
            )
            
            logger.info(f"Password analysis completed - Pwned: {result.is_pwned}")
            return response
    
    except Exception as e:
        logger.error(f"Failed to analyze password: {str(e)}")
        if "Rate limit" in str(e):
            raise HTTPException(status_code=429, detail=str(e))
        else:
            raise HTTPException(status_code=500, detail=f"Failed to analyze password: {str(e)}")


# Bulk endpoints for efficiency
@router.post("/bulk/emails", response_model=BulkAnalysisResponse)
async def bulk_email_check(request: BulkEmailCheckRequest):
    """
    Bulk email breach checking
    
    Args:
        request: Bulk email check request
        
    Returns:
        Bulk analysis results
    """
    logger.info(f"Performing bulk email check for {len(request.emails)} emails")
    start_time = datetime.now()
    
    results = []
    failed_items = []
    
    settings = get_settings()
    async with HaveIBeenPwnedClient(api_key=settings.haveibeenpwned_api_key) as client:
        for email in request.emails:
            try:
                # Individual email analysis
                email_request = BreachedAccountRequest(
                    email=email,
                    truncate_response=not request.include_breach_details
                )
                
                analysis_result = await analyze_email(email_request)
                results.append(analysis_result)
                
            except Exception as e:
                logger.error(f"Failed to analyze email {email}: {str(e)}")
                failed_items.append({
                    "item": str(email),
                    "error": str(e)
                })
    
    end_time = datetime.now()
    processing_time = (end_time - start_time).total_seconds()
    
    response = BulkAnalysisResponse(
        total_items=len(request.emails),
        items_processed=len(results),
        items_failed=len(failed_items),
        analysis_results=results,
        failed_items=failed_items,
        processing_time_seconds=processing_time,
        summary={}  # Will be generated by validator
    )
    
    logger.info(f"Bulk email check completed - {len(results)} successful, {len(failed_items)} failed")
    return response