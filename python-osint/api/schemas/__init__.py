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

__all__ = [
    'AnalysisType', 'VerdictType',
    'VirusTotalFileRequest', 'VirusTotalURLRequest', 'VirusTotalHashRequest',
    'VirusTotalDomainRequest', 'VirusTotalIPRequest', 'VirusTotalSearchRequest',
    'VirusTotalAnalysisResult', 'VirusTotalBulkResult', 'VirusTotalServiceInfo',
    'VirusTotalConfig', 'AnalysisStats', 'EngineResult'
]