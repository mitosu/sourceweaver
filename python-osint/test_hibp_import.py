#!/usr/bin/env python3

# Test script to check HaveIBeenPwned router import
import sys
import os

# Add the current directory to Python path
sys.path.insert(0, '/app')

try:
    print("Testing HaveIBeenPwned imports...")
    
    # Test client import
    from api.clients.haveibeenpwned_client import HaveIBeenPwnedClient
    print("✓ HaveIBeenPwnedClient imported successfully")
    
    # Test schemas import
    from api.schemas.haveibeenpwned_schemas import BreachedAccountRequest
    print("✓ HaveIBeenPwned schemas imported successfully")
    
    # Test router import
    from api.routers.haveibeenpwned_router import router
    print("✓ HaveIBeenPwned router imported successfully")
    print(f"  - Router prefix: {router.prefix}")
    print(f"  - Router tags: {router.tags}")
    
    # List routes
    print("  - Available routes:")
    for route in router.routes:
        if hasattr(route, 'path') and hasattr(route, 'methods'):
            print(f"    {route.methods} {route.path}")
    
    print("\nAll imports successful!")
    
except Exception as e:
    print(f"❌ Import failed: {str(e)}")
    import traceback
    traceback.print_exc()