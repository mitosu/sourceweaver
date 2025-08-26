"""
Analysis Manager for OSINT scripts coordination
"""

import asyncio
import json
import logging
import time
import uuid
from pathlib import Path
from typing import Dict, Any, List, Optional
import subprocess
import sys

from .models import AnalysisRequest, AnalysisResponse, ScriptInfo, AnalysisStatus
from .config import Settings, SCRIPT_MAPPING

logger = logging.getLogger(__name__)

class AnalysisManager:
    """Manages analysis script execution and coordination"""
    
    def __init__(self, settings: Settings):
        self.settings = settings
        self.scripts_path = Path(settings.scripts_path)
        self.active_tasks: Dict[str, Any] = {}
        self.stats = {
            'total_requests': 0,
            'successful_analyses': 0,
            'failed_analyses': 0,
            'start_time': time.time()
        }
        
    async def initialize(self):
        """Initialize the analysis manager"""
        # Ensure scripts directory exists
        self.scripts_path.mkdir(parents=True, exist_ok=True)
        
        # Validate available scripts
        await self._validate_scripts()
        logger.info(f"Analysis manager initialized with {len(SCRIPT_MAPPING)} script types")
    
    async def cleanup(self):
        """Cleanup resources"""
        # Cancel any running tasks
        for task_id, task_info in self.active_tasks.items():
            if 'process' in task_info and task_info['process'].poll() is None:
                task_info['process'].terminate()
                logger.info(f"Terminated running task: {task_id}")
        self.active_tasks.clear()
    
    async def _validate_scripts(self):
        """Validate that required scripts exist"""
        for target_type, script_name in SCRIPT_MAPPING.items():
            script_path = self.scripts_path / script_name
            if not script_path.exists():
                logger.warning(f"Script not found: {script_path}")
            else:
                logger.debug(f"Found script: {script_path}")
    
    async def health_check(self) -> Dict[str, Any]:
        """Perform health check of all services"""
        health = {
            'status': 'healthy',
            'scripts': {},
            'uptime': time.time() - self.stats['start_time']
        }
        
        # Check script availability
        for target_type, script_name in SCRIPT_MAPPING.items():
            script_path = self.scripts_path / script_name
            health['scripts'][target_type] = {
                'available': script_path.exists(),
                'path': str(script_path)
            }
        
        return health
    
    async def get_available_scripts(self) -> List[ScriptInfo]:
        """Get list of available analysis scripts"""
        scripts = []
        
        for target_type, script_name in SCRIPT_MAPPING.items():
            script_path = self.scripts_path / script_name
            
            if script_path.exists():
                # Try to extract script metadata
                try:
                    script_info = await self._get_script_info(script_path, target_type)
                    scripts.append(script_info)
                except Exception as e:
                    logger.warning(f"Could not get info for {script_name}: {e}")
                    # Fallback script info
                    scripts.append(ScriptInfo(
                        name=script_name,
                        target_type=target_type,
                        description=f"Analysis script for {target_type} targets"
                    ))
        
        return scripts
    
    async def _get_script_info(self, script_path: Path, target_type: str) -> ScriptInfo:
        """Extract metadata from analysis script"""
        # Try to get script info by running with --info flag
        try:
            process = await asyncio.create_subprocess_exec(
                sys.executable, str(script_path), '--info',
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
                timeout=10
            )
            stdout, _ = await process.communicate()
            
            if process.returncode == 0:
                info = json.loads(stdout.decode())
                return ScriptInfo(**info)
        except:
            pass
        
        # Fallback to basic info
        return ScriptInfo(
            name=script_path.name,
            target_type=target_type,
            description=f"OSINT analysis for {target_type} targets"
        )
    
    async def is_target_type_supported(self, target_type: str) -> bool:
        """Check if target type is supported"""
        return target_type in SCRIPT_MAPPING
    
    async def analyze_target(self, request: AnalysisRequest) -> AnalysisResponse:
        """Perform synchronous target analysis"""
        start_time = time.time()
        self.stats['total_requests'] += 1
        
        try:
            logger.info(f"Starting analysis: {request.target_type} -> {request.target_value}")
            
            script_name = SCRIPT_MAPPING.get(request.target_type)
            if not script_name:
                raise ValueError(f"Unsupported target type: {request.target_type}")
            
            script_path = self.scripts_path / script_name
            if not script_path.exists():
                raise FileNotFoundError(f"Script not found: {script_name}")
            
            # Execute script
            result_data = await self._execute_script(script_path, request)
            
            execution_time = time.time() - start_time
            self.stats['successful_analyses'] += 1
            
            return AnalysisResponse(
                status=AnalysisStatus.SUCCESS,
                data=result_data,
                execution_time=execution_time,
                timestamp=time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())
            )
            
        except Exception as e:
            execution_time = time.time() - start_time
            self.stats['failed_analyses'] += 1
            logger.error(f"Analysis failed: {e}")
            
            return AnalysisResponse(
                status=AnalysisStatus.ERROR,
                data={},
                error=str(e),
                execution_time=execution_time,
                timestamp=time.strftime('%Y-%m-%d %H:%M:%S UTC', time.gmtime())
            )
    
    async def _execute_script(self, script_path: Path, request: AnalysisRequest) -> Dict[str, Any]:
        """Execute analysis script and return results"""
        
        # Prepare command
        cmd = [
            sys.executable,
            str(script_path),
            request.target_value,
            '--format=json'
        ]
        
        # Prepare environment with API keys
        env = self.settings.get_script_env()
        
        # Add request-specific config to environment
        for key, value in request.config.items():
            env[key.upper()] = str(value)
        
        try:
            # Execute script with timeout
            process = await asyncio.create_subprocess_exec(
                *cmd,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE,
                env=env
            )
            
            try:
                stdout, stderr = await asyncio.wait_for(
                    process.communicate(),
                    timeout=request.timeout
                )
            except asyncio.TimeoutError:
                process.terminate()
                await process.wait()
                raise TimeoutError(f"Script execution timed out after {request.timeout} seconds")
            
            if process.returncode != 0:
                error_msg = stderr.decode() if stderr else "Script execution failed"
                raise RuntimeError(f"Script failed with return code {process.returncode}: {error_msg}")
            
            # Parse JSON output
            output = stdout.decode()
            try:
                result = json.loads(output)
                return result
            except json.JSONDecodeError as e:
                logger.error(f"Invalid JSON output from script: {output[:500]}")
                raise ValueError(f"Script returned invalid JSON: {e}")
                
        except Exception as e:
            logger.error(f"Script execution error: {e}")
            raise
    
    async def start_async_analysis(self, request: AnalysisRequest) -> str:
        """Start asynchronous analysis and return task ID"""
        task_id = str(uuid.uuid4())
        
        # Create task entry
        self.active_tasks[task_id] = {
            'status': AnalysisStatus.PENDING,
            'request': request,
            'start_time': time.time(),
            'result': None
        }
        
        # Start analysis in background
        asyncio.create_task(self._run_async_analysis(task_id))
        
        return task_id
    
    async def _run_async_analysis(self, task_id: str):
        """Run asynchronous analysis"""
        task_info = self.active_tasks[task_id]
        task_info['status'] = AnalysisStatus.PROCESSING
        
        try:
            result = await self.analyze_target(task_info['request'])
            task_info['result'] = result
            task_info['status'] = result.status
        except Exception as e:
            logger.error(f"Async analysis failed for task {task_id}: {e}")
            task_info['result'] = AnalysisResponse(
                status=AnalysisStatus.ERROR,
                data={},
                error=str(e),
                execution_time=time.time() - task_info['start_time']
            )
            task_info['status'] = AnalysisStatus.ERROR
    
    async def get_analysis_result(self, task_id: str) -> Optional[AnalysisResponse]:
        """Get result of asynchronous analysis"""
        task_info = self.active_tasks.get(task_id)
        if not task_info:
            return None
        
        if task_info['status'] in [AnalysisStatus.SUCCESS, AnalysisStatus.ERROR]:
            # Return result and cleanup
            result = task_info['result']
            del self.active_tasks[task_id]
            return result
        else:
            # Still processing
            return AnalysisResponse(
                status=task_info['status'],
                data={},
                execution_time=time.time() - task_info['start_time'],
                task_id=task_id
            )
    
    async def analyze_bulk_targets(self, requests: List[AnalysisRequest], max_concurrent: int = 5) -> List[AnalysisResponse]:
        """Analyze multiple targets concurrently"""
        semaphore = asyncio.Semaphore(max_concurrent)
        
        async def analyze_with_semaphore(request: AnalysisRequest) -> AnalysisResponse:
            async with semaphore:
                return await self.analyze_target(request)
        
        tasks = [analyze_with_semaphore(request) for request in requests]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        
        # Convert exceptions to error responses
        processed_results = []
        for result in results:
            if isinstance(result, Exception):
                processed_results.append(AnalysisResponse(
                    status=AnalysisStatus.ERROR,
                    data={},
                    error=str(result),
                    execution_time=0.0
                ))
            else:
                processed_results.append(result)
        
        return processed_results
    
    async def get_stats(self) -> Dict[str, Any]:
        """Get API statistics"""
        uptime = time.time() - self.stats['start_time']
        total = self.stats['total_requests']
        
        return {
            'total_requests': total,
            'successful_analyses': self.stats['successful_analyses'],
            'failed_analyses': self.stats['failed_analyses'],
            'success_rate': (self.stats['successful_analyses'] / total * 100) if total > 0 else 0,
            'uptime_seconds': uptime,
            'active_tasks': len(self.active_tasks),
            'available_scripts': len(SCRIPT_MAPPING)
        }
    
    async def test_script(self, script_name: str, test_value: str) -> Dict[str, Any]:
        """Test a specific script with a test value"""
        script_path = self.scripts_path / script_name
        
        if not script_path.exists():
            raise FileNotFoundError(f"Script not found: {script_name}")
        
        # Determine target type from script name
        target_type = None
        for t_type, s_name in SCRIPT_MAPPING.items():
            if s_name == script_name:
                target_type = t_type
                break
        
        if not target_type:
            raise ValueError(f"Unknown script type: {script_name}")
        
        # Create test request
        test_request = AnalysisRequest(
            target_type=target_type,
            target_value=test_value,
            timeout=30
        )
        
        return await self._execute_script(script_path, test_request)