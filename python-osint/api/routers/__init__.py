"""
API Routers for external services
"""

from .virustotal_router import router as virustotal_router
from .google_search_router import router as google_search_router
from .dorking_router import router as dorking_router
from .haveibeenpwned_router import router as haveibeenpwned_router
from .alias_search_router import router as alias_search_router

__all__ = ['virustotal_router', 'google_search_router', 'dorking_router', 'haveibeenpwned_router', 'alias_search_router']