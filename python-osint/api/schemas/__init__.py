"""
API Schemas for external services
"""

from .virustotal_schemas import (
    AnalysisType, VerdictType,
    VirusTotalFileRequest, VirusTotalURLRequest, VirusTotalHashRequest,
    VirusTotalDomainRequest, VirusTotalIPRequest, VirusTotalSearchRequest,
    VirusTotalAnalysisResult, VirusTotalBulkResult, VirusTotalServiceInfo,
    VirusTotalConfig, AnalysisStats, EngineResult
)

from .google_search_schemas import (
    GoogleSearchRequest, GoogleImageSearchRequest, GoogleSiteSearchRequest,
    SearchInformation, SearchItem, SearchContext,
    GoogleSearchResult, GoogleSearchSummary, GoogleSearchInfo, GoogleSearchError,
    BulkSearchRequest, BulkSearchResult
)

from .alias_search_schemas import (
    SocialPlatform, SearchPriority,
    AliasSearchRequest, PlatformResult, AliasSearchSummary, AliasSearchResult,
    AliasSearchError, BulkAliasSearchRequest, BulkAliasSearchResult
)

__all__ = [
    # VirusTotal schemas
    'AnalysisType', 'VerdictType',
    'VirusTotalFileRequest', 'VirusTotalURLRequest', 'VirusTotalHashRequest',
    'VirusTotalDomainRequest', 'VirusTotalIPRequest', 'VirusTotalSearchRequest',
    'VirusTotalAnalysisResult', 'VirusTotalBulkResult', 'VirusTotalServiceInfo',
    'VirusTotalConfig', 'AnalysisStats', 'EngineResult',
    # Google Search schemas
    'GoogleSearchRequest', 'GoogleImageSearchRequest', 'GoogleSiteSearchRequest',
    'SearchInformation', 'SearchItem', 'SearchContext',
    'GoogleSearchResult', 'GoogleSearchSummary', 'GoogleSearchInfo', 'GoogleSearchError',
    'BulkSearchRequest', 'BulkSearchResult',
    # Alias Search schemas
    'SocialPlatform', 'SearchPriority',
    'AliasSearchRequest', 'PlatformResult', 'AliasSearchSummary', 'AliasSearchResult',
    'AliasSearchError', 'BulkAliasSearchRequest', 'BulkAliasSearchResult'
]