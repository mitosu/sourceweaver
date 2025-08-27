#!/usr/bin/env python3
"""
VirusTotal Setup and Configuration Script
Helps set up and test VirusTotal API integration
"""

import os
import sys
import asyncio
import logging
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from api.clients import VirusTotalClient, VirusTotalAPIError

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

def get_api_key():
    """Get API key from environment or user input"""
    api_key = os.getenv('VIRUSTOTAL_API_KEY')
    
    if not api_key:
        print("\n🔐 VirusTotal API Key Setup")
        print("=" * 50)
        print("To use VirusTotal integration, you need an API key.")
        print("You can get one for free at: https://www.virustotal.com/gui/my-apikey")
        print("\nFree tier includes:")
        print("- 4 requests per minute")
        print("- 500 requests per day") 
        print("- 15,500 requests per month")
        print()
        
        api_key = input("Enter your VirusTotal API key: ").strip()
        
        if not api_key:
            logger.error("No API key provided")
            return None
    
    return api_key

async def test_api_key(api_key):
    """Test if the API key is valid"""
    logger.info("Testing VirusTotal API key...")
    
    try:
        client = VirusTotalClient(api_key)
        async with client:
            is_valid = await client.test_connection()
            
            if is_valid:
                logger.info("✅ API key is valid!")
                
                # Get some basic info
                try:
                    # Test with a simple IP lookup
                    result = await client.get_ip_info("8.8.8.8")
                    logger.info("✅ API is working correctly")
                    return True
                except Exception as e:
                    logger.warning(f"⚠️ API key valid but test query failed: {e}")
                    return True
            else:
                logger.error("❌ API key is invalid")
                return False
                
    except VirusTotalAPIError as e:
        logger.error(f"❌ VirusTotal API error: {e.message}")
        return False
    except Exception as e:
        logger.error(f"❌ Test failed: {e}")
        return False

def create_env_file(api_key):
    """Create or update .env file with API key"""
    env_file = Path(__file__).parent.parent / '.env'
    env_example_file = Path(__file__).parent.parent / '.env.example'
    
    # Read existing .env or create from example
    env_content = ""
    if env_file.exists():
        with open(env_file, 'r') as f:
            env_content = f.read()
    elif env_example_file.exists():
        with open(env_example_file, 'r') as f:
            env_content = f.read()
    
    # Update or add VIRUSTOTAL_API_KEY
    lines = env_content.split('\n')
    updated = False
    
    for i, line in enumerate(lines):
        if line.startswith('VIRUSTOTAL_API_KEY='):
            lines[i] = f'VIRUSTOTAL_API_KEY="{api_key}"'
            updated = True
            break
    
    if not updated:
        lines.append(f'VIRUSTOTAL_API_KEY="{api_key}"')
    
    # Write back to .env
    with open(env_file, 'w') as f:
        f.write('\n'.join(lines))
    
    logger.info(f"✅ API key saved to {env_file}")
    return True

def show_usage_info():
    """Show usage information for VirusTotal integration"""
    print("\n📋 VirusTotal Integration Usage")
    print("=" * 50)
    print("Available endpoints:")
    print()
    print("🏥 Health Check:")
    print("   GET /api/v1/virustotal/health")
    print()
    print("📁 File Analysis:")
    print("   POST /api/v1/virustotal/files/analyze  (upload file)")
    print("   GET  /api/v1/virustotal/files/{hash}   (get file report)")
    print("   GET  /api/v1/virustotal/files/{hash}/behaviours")
    print()
    print("🌐 URL Analysis:")
    print("   POST /api/v1/virustotal/urls/analyze")
    print("   GET  /api/v1/virustotal/urls/{url_id}")
    print("   POST /api/v1/virustotal/urls/analyze-by-url")
    print()
    print("🌍 Domain Analysis:")
    print("   GET /api/v1/virustotal/domains/{domain}")
    print("   GET /api/v1/virustotal/domains/{domain}/subdomains")
    print("   GET /api/v1/virustotal/domains/{domain}/resolutions")
    print()
    print("🖥️ IP Analysis:")
    print("   GET /api/v1/virustotal/ip/{ip_address}")
    print("   GET /api/v1/virustotal/ip/{ip_address}/resolutions")
    print()
    print("🔍 Search:")
    print("   POST /api/v1/virustotal/search")
    print()
    print("📊 Bulk Analysis:")
    print("   POST /api/v1/virustotal/bulk/hashes")
    print()
    print("📈 Statistics:")
    print("   GET /api/v1/virustotal/stats")
    print()

def show_rate_limits():
    """Show rate limit information"""
    print("\n⏱️ VirusTotal Rate Limits")
    print("=" * 50)
    print("Free Account:")
    print("- 4 requests per minute")
    print("- 500 requests per day")
    print("- 15,500 requests per month")
    print()
    print("Premium Account:")
    print("- Higher rate limits")
    print("- Additional features")
    print("- Priority support")
    print()
    print("⚠️ Important: The API client includes automatic rate limiting")
    print("   to prevent quota violations.")
    print()

def show_security_tips():
    """Show security tips for API key management"""
    print("\n🔒 Security Best Practices")
    print("=" * 50)
    print("1. Never commit API keys to version control")
    print("2. Use environment variables for API keys")
    print("3. Rotate API keys periodically")
    print("4. Monitor API usage in VirusTotal dashboard")
    print("5. Use different API keys for different environments")
    print()
    print("✅ This script automatically saves your API key to .env file")
    print("   Make sure .env is in your .gitignore!")
    print()

async def main():
    """Main setup function"""
    print("🦠 VirusTotal API Setup Wizard")
    print("=" * 50)
    print("This script will help you set up VirusTotal integration")
    print()
    
    # Get API key
    api_key = get_api_key()
    if not api_key:
        logger.error("Setup cancelled - no API key provided")
        return False
    
    # Test API key
    print("\n🧪 Testing API Key...")
    is_valid = await test_api_key(api_key)
    
    if not is_valid:
        logger.error("Setup failed - invalid API key")
        return False
    
    # Save to .env file
    print("\n💾 Saving Configuration...")
    create_env_file(api_key)
    
    # Show information
    show_usage_info()
    show_rate_limits()
    show_security_tips()
    
    print("🎉 VirusTotal integration setup completed successfully!")
    print()
    print("Next steps:")
    print("1. Start the API server: python api/main.py")
    print("2. Visit http://localhost:8001/docs for API documentation")
    print("3. Test the integration: python tests/test_virustotal_integration.py")
    
    return True

if __name__ == "__main__":
    try:
        success = asyncio.run(main())
        sys.exit(0 if success else 1)
    except KeyboardInterrupt:
        print("\n\n⚠️ Setup cancelled by user")
        sys.exit(1)
    except Exception as e:
        logger.error(f"Setup failed: {e}")
        sys.exit(1)