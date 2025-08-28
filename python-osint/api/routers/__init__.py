"""
API Routers for external services
"""

from .virustotal_router import router as virustotal_router
from .google_search_router import router as google_search_router
from .dorking_router import router as dorking_router

__all__ = ['virustotal_router', 'google_search_router', 'dorking_router']