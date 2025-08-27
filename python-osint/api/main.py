#!/usr/bin/env python3
"""
FastAPI OSINT Analysis Microservice
Main application entry point
"""

import os
import sys
import asyncio
import logging
from pathlib import Path
from typing import Dict, Any, List, Optional

from fastapi import FastAPI, HTTPException, BackgroundTasks, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field
import uvicorn

# Add scripts directory to path
scripts_dir = Path(__file__).parent.parent / "scripts"
sys.path.insert(0, str(scripts_dir))

from api.models import AnalysisRequest, AnalysisResponse, HealthResponse, ScriptInfo
from api.analysis_manager import AnalysisManager
from api.config import get_settings
from api.routers import virustotal_router

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/app/logs/osint-api.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Initialize FastAPI app
app = FastAPI(
    title="OSINT Analysis API",
    description="Microservice for OSINT analysis using Python scripts",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure appropriately for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize analysis manager
settings = get_settings()
analysis_manager = AnalysisManager(settings)

# Include routers
app.include_router(virustotal_router, prefix="/api/v1")

@app.on_event("startup")
async def startup_event():
    """Initialize services on startup"""
    logger.info("Starting OSINT Analysis API...")
    await analysis_manager.initialize()
    logger.info("OSINT Analysis API started successfully")

@app.on_event("shutdown")
async def shutdown_event():
    """Cleanup on shutdown"""
    logger.info("Shutting down OSINT Analysis API...")
    await analysis_manager.cleanup()
    logger.info("OSINT Analysis API stopped")

@app.get("/", response_model=Dict[str, str])
async def root():
    """Root endpoint with API information"""
    return {
        "service": "OSINT Analysis API",
        "version": "1.0.0",
        "status": "running",
        "docs": "/docs"
    }

@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint for Docker/Kubernetes"""
    try:
        health_status = await analysis_manager.health_check()
        return HealthResponse(
            status="healthy",
            version="1.0.0",
            services=health_status
        )
    except Exception as e:
        logger.error(f"Health check failed: {e}")
        raise HTTPException(status_code=503, detail="Service unhealthy")

@app.get("/scripts", response_model=List[ScriptInfo])
async def list_available_scripts():
    """List all available analysis scripts"""
    try:
        scripts = await analysis_manager.get_available_scripts()
        return scripts
    except Exception as e:
        logger.error(f"Error listing scripts: {e}")
        raise HTTPException(status_code=500, detail="Failed to list scripts")

@app.post("/analyze", response_model=AnalysisResponse)
async def analyze_target(
    request: AnalysisRequest,
    background_tasks: BackgroundTasks
):
    """
    Main endpoint for target analysis
    Supports both synchronous and asynchronous analysis
    """
    try:
        logger.info(f"Analysis request: {request.target_type} -> {request.target_value}")
        
        # Validate target type
        if not await analysis_manager.is_target_type_supported(request.target_type):
            raise HTTPException(
                status_code=400, 
                detail=f"Unsupported target type: {request.target_type}"
            )
        
        # Perform analysis
        if request.async_analysis:
            # Start async analysis
            task_id = await analysis_manager.start_async_analysis(request)
            return AnalysisResponse(
                status="processing",
                task_id=task_id,
                data={},
                execution_time=0.0
            )
        else:
            # Synchronous analysis
            result = await analysis_manager.analyze_target(request)
            return result
            
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Analysis failed: {e}", exc_info=True)
        return AnalysisResponse(
            status="error",
            data={},
            error=str(e),
            execution_time=0.0
        )

@app.get("/analyze/{task_id}", response_model=AnalysisResponse)
async def get_analysis_result(task_id: str):
    """Get result of asynchronous analysis"""
    try:
        result = await analysis_manager.get_analysis_result(task_id)
        if not result:
            raise HTTPException(status_code=404, detail="Task not found")
        return result
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error retrieving task result: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve result")

@app.post("/analyze/bulk", response_model=List[AnalysisResponse])
async def analyze_bulk_targets(
    requests: List[AnalysisRequest],
    background_tasks: BackgroundTasks,
    max_concurrent: int = 5
):
    """
    Bulk analysis endpoint for multiple targets
    """
    if len(requests) > 100:  # Limit bulk requests
        raise HTTPException(status_code=400, detail="Too many targets (max 100)")
    
    try:
        results = await analysis_manager.analyze_bulk_targets(requests, max_concurrent)
        return results
    except Exception as e:
        logger.error(f"Bulk analysis failed: {e}")
        raise HTTPException(status_code=500, detail="Bulk analysis failed")

@app.get("/stats", response_model=Dict[str, Any])
async def get_api_stats():
    """Get API usage statistics"""
    try:
        stats = await analysis_manager.get_stats()
        return stats
    except Exception as e:
        logger.error(f"Error getting stats: {e}")
        raise HTTPException(status_code=500, detail="Failed to get statistics")

@app.post("/test/{script_name}")
async def test_script(script_name: str, test_value: str = "8.8.8.8"):
    """Test a specific analysis script"""
    try:
        result = await analysis_manager.test_script(script_name, test_value)
        return {"status": "success", "result": result}
    except Exception as e:
        logger.error(f"Script test failed: {e}")
        return {"status": "error", "error": str(e)}

# Custom exception handlers
@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    """Global exception handler"""
    logger.error(f"Unhandled exception: {exc}", exc_info=True)
    return JSONResponse(
        status_code=500,
        content={"detail": "Internal server error"}
    )

if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8001,
        reload=True,
        log_level="info"
    )