#!/usr/bin/env python3
"""
FastAPI service for OSINT analysis scripts
This creates a REST API that Symfony can call
"""

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any, Optional
import asyncio
import json
import subprocess
import os

app = FastAPI(title="OSINT Analysis API", version="1.0.0")

class AnalysisRequest(BaseModel):
    target_type: str
    target_value: str
    config: Optional[Dict[str, Any]] = {}

class AnalysisResponse(BaseModel):
    status: str
    data: Dict[str, Any]
    error: Optional[str] = None
    execution_time: float

@app.post("/analyze", response_model=AnalysisResponse)
async def analyze_target(request: AnalysisRequest):
    """
    Main endpoint for target analysis
    """
    try:
        script_name = get_script_for_target(request.target_type)
        if not script_name:
            raise HTTPException(status_code=400, detail=f"Unsupported target type: {request.target_type}")
        
        result = await execute_analysis_script(
            script_name, 
            request.target_value, 
            request.config
        )
        
        return AnalysisResponse(
            status="success",
            data=result["data"],
            execution_time=result["execution_time"]
        )
        
    except Exception as e:
        return AnalysisResponse(
            status="error",
            data={},
            error=str(e),
            execution_time=0.0
        )

@app.get("/scripts")
async def list_available_scripts():
    """
    List all available analysis scripts
    """
    scripts_dir = os.path.join(os.path.dirname(__file__), "scripts")
    scripts = []
    
    if os.path.exists(scripts_dir):
        for file in os.listdir(scripts_dir):
            if file.endswith('.py') and not file.startswith('__'):
                scripts.append({
                    "name": file,
                    "target_type": file.replace('_analysis.py', '').replace('.py', '')
                })
    
    return {"scripts": scripts}

def get_script_for_target(target_type: str) -> Optional[str]:
    """
    Map target types to script files
    """
    script_mapping = {
        'ip': 'ip_analysis.py',
        'domain': 'domain_analysis.py', 
        'url': 'url_analysis.py',
        'email': 'email_analysis.py',
        'hash': 'hash_analysis.py'
    }
    
    return script_mapping.get(target_type)

async def execute_analysis_script(script_name: str, target_value: str, config: Dict[str, Any]) -> Dict[str, Any]:
    """
    Execute Python analysis script asynchronously
    """
    import time
    start_time = time.time()
    
    scripts_dir = os.path.join(os.path.dirname(__file__), "scripts")
    script_path = os.path.join(scripts_dir, script_name)
    
    if not os.path.exists(script_path):
        raise FileNotFoundError(f"Script not found: {script_name}")
    
    # Prepare environment variables for API keys
    env = os.environ.copy()
    for key, value in config.items():
        env[key.upper()] = str(value)
    
    # Execute script
    process = await asyncio.create_subprocess_exec(
        'python3', script_path, target_value, '--format=json',
        stdout=asyncio.subprocess.PIPE,
        stderr=asyncio.subprocess.PIPE,
        env=env
    )
    
    stdout, stderr = await process.communicate()
    
    if process.returncode != 0:
        raise RuntimeError(f"Script execution failed: {stderr.decode()}")
    
    try:
        result_data = json.loads(stdout.decode())
    except json.JSONDecodeError as e:
        raise ValueError(f"Invalid JSON output from script: {e}")
    
    execution_time = time.time() - start_time
    
    return {
        "data": result_data,
        "execution_time": execution_time
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)