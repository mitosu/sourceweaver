#!/usr/bin/env python3

import sys
import traceback

try:
    print("=== Testing imports step by step ===")
    
    print("1. Testing basic imports...")
    from fastapi import APIRouter
    print("✓ FastAPI imported")
    
    print("2. Testing client import...")
    from api.clients.haveibeenpwned_client import HaveIBeenPwnedClient
    print("✓ HaveIBeenPwnedClient imported")
    
    print("3. Testing schemas import...")
    from api.schemas.haveibeenpwned_schemas import BreachedAccountRequest
    print("✓ Schemas imported")
    
    print("4. Testing router import...")
    from api.routers.haveibeenpwned_router import router
    print(f"✓ Router imported - prefix: {router.prefix}, routes: {len(router.routes)}")
    
    print("5. Testing main router import...")
    from api.routers import haveibeenpwned_router
    print("✓ Router module import successful")
    
    print("\n=== All imports successful! ===")
    
except Exception as e:
    print(f"❌ Import failed at step: {str(e)}")
    print("\nFull traceback:")
    traceback.print_exc()
    sys.exit(1)