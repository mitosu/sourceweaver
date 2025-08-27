"""
API Routers for external services
"""

from .virustotal_router import router as virustotal_router

__all__ = ['virustotal_router']