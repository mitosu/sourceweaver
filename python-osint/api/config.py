"""
Configuration management for OSINT Analysis API
"""

import os
from functools import lru_cache
from typing import Dict, Any, Optional
from pydantic_settings import BaseSettings
from pydantic import Field

class Settings(BaseSettings):
    """Application settings loaded from environment variables"""
    
    # API Configuration
    api_title: str = Field(default="OSINT Analysis API", env="API_TITLE")
    api_version: str = Field(default="1.0.0", env="API_VERSION")
    debug: bool = Field(default=False, env="DEBUG")
    log_level: str = Field(default="INFO", env="LOG_LEVEL")
    
    # Server Configuration
    host: str = Field(default="0.0.0.0", env="HOST")
    port: int = Field(default=8001, env="PORT")
    workers: int = Field(default=1, env="WORKERS")
    
    # Analysis Configuration
    default_timeout: int = Field(default=120, env="DEFAULT_TIMEOUT")
    max_concurrent_analyses: int = Field(default=10, env="MAX_CONCURRENT_ANALYSES")
    enable_async_analysis: bool = Field(default=True, env="ENABLE_ASYNC_ANALYSIS")
    
    # API Keys (loaded from environment)
    virustotal_api_key: Optional[str] = Field(None, env="VIRUSTOTAL_API_KEY")
    abuseipdb_api_key: Optional[str] = Field(None, env="ABUSEIPDB_API_KEY")
    urlvoid_api_key: Optional[str] = Field(None, env="URLVOID_API_KEY")
    urlvoid_identifier: Optional[str] = Field(None, env="URLVOID_IDENTIFIER")
    shodan_api_key: Optional[str] = Field(None, env="SHODAN_API_KEY")
    google_api_key: Optional[str] = Field(None, env="GOOGLE_API_KEY")
    google_cse_id: Optional[str] = Field(None, env="GOOGLE_CSE_ID")
    google_calls_per_day: int = Field(default=100, env="GOOGLE_CALLS_PER_DAY")
    google_calls_per_minute: int = Field(default=50, env="GOOGLE_CALLS_PER_MINUTE")
    haveibeenpwned_api_key: Optional[str] = Field(None, env="HAVEIBEENPWNED_API_KEY")
    
    # Paths
    scripts_path: str = Field(default="/app/scripts", env="SCRIPTS_PATH")
    logs_path: str = Field(default="/app/logs", env="LOGS_PATH")
    temp_path: str = Field(default="/tmp/osint", env="TEMP_PATH")
    
    # Security
    cors_origins: list = Field(default=["*"], env="CORS_ORIGINS")
    enable_metrics: bool = Field(default=True, env="ENABLE_METRICS")
    
    class Config:
        env_file = ".env"
        env_file_encoding = "utf-8"
        case_sensitive = False
    
    def get_api_config(self) -> Dict[str, Any]:
        """Get API configuration dictionary for scripts"""
        return {
            'virustotal_api_key': self.virustotal_api_key,
            'abuseipdb_api_key': self.abuseipdb_api_key,
            'urlvoid_api_key': self.urlvoid_api_key,
            'urlvoid_identifier': self.urlvoid_identifier,
            'shodan_api_key': self.shodan_api_key,
            'google_api_key': self.google_api_key,
            'google_cse_id': self.google_cse_id,
            'haveibeenpwned_api_key': self.haveibeenpwned_api_key,
        }
    
    def get_script_env(self) -> Dict[str, str]:
        """Get environment variables for script execution"""
        env = os.environ.copy()
        
        # Add API keys to environment
        if self.virustotal_api_key:
            env['VIRUSTOTAL_API_KEY'] = self.virustotal_api_key
        if self.abuseipdb_api_key:
            env['ABUSEIPDB_API_KEY'] = self.abuseipdb_api_key
        if self.urlvoid_api_key:
            env['URLVOID_API_KEY'] = self.urlvoid_api_key
        if self.urlvoid_identifier:
            env['URLVOID_IDENTIFIER'] = self.urlvoid_identifier
        if self.shodan_api_key:
            env['SHODAN_API_KEY'] = self.shodan_api_key
        if self.google_api_key:
            env['GOOGLE_API_KEY'] = self.google_api_key
        if self.google_cse_id:
            env['GOOGLE_CSE_ID'] = self.google_cse_id
        if self.haveibeenpwned_api_key:
            env['HAVEIBEENPWNED_API_KEY'] = self.haveibeenpwned_api_key
        
        return env

@lru_cache()
def get_settings() -> Settings:
    """Get cached settings instance"""
    return Settings()

# API Configuration constants
SUPPORTED_TARGET_TYPES = ['ip', 'domain', 'url', 'email', 'hash', 'phone']

SCRIPT_MAPPING = {
    'ip': 'ip_analysis.py',
    'domain': 'domain_analysis.py',
    'url': 'url_analysis.py',
    'email': 'email_analysis.py',
    'hash': 'hash_analysis.py',
    'phone': 'phone_analysis.py'
}

# Rate limiting configuration
RATE_LIMITS = {
    'default': 60,  # requests per minute
    'virustotal': 4,  # requests per minute (free tier)
    'abuseipdb': 1000,  # requests per day (free tier)
    'urlvoid': 100,  # requests per day (free tier)
    'shodan': 100,  # requests per month (free tier)
    'google_search': 100  # requests per day (free tier)
}