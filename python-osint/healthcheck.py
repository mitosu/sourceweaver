#!/usr/bin/env python3
"""
Health check script for Docker container
"""

import requests
import sys
import time

def check_health():
    """Check if the FastAPI service is healthy"""
    max_attempts = 3
    
    for attempt in range(max_attempts):
        try:
            response = requests.get('http://localhost:8001/health', timeout=10)
            if response.status_code == 200:
                data = response.json()
                if data.get('status') == 'healthy':
                    print("Service is healthy")
                    return True
            print(f"Health check failed: {response.status_code}")
        except Exception as e:
            print(f"Health check attempt {attempt + 1} failed: {e}")
            
        if attempt < max_attempts - 1:
            time.sleep(2)
    
    return False

if __name__ == '__main__':
    if check_health():
        sys.exit(0)
    else:
        sys.exit(1)