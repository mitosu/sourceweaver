#!/usr/bin/env python3
"""
VirusTotal Integration Tests
Tests the VirusTotal client and API endpoints
"""

import asyncio
import os
import sys
import logging
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from api.clients import VirusTotalClient, VirusTotalAPIError
from api.config import get_settings

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

class VirusTotalTester:
    """Test class for VirusTotal integration"""
    
    def __init__(self):
        self.settings = get_settings()
        self.client = None
        
    async def setup(self):
        """Setup test environment"""
        if not self.settings.virustotal_api_key:
            raise ValueError("VIRUSTOTAL_API_KEY environment variable not set")
        
        self.client = VirusTotalClient(self.settings.virustotal_api_key)
        logger.info("VirusTotal client initialized")
    
    async def test_connection(self):
        """Test API connection and authentication"""
        logger.info("Testing VirusTotal API connection...")
        
        try:
            async with self.client:
                is_connected = await self.client.test_connection()
                if is_connected:
                    logger.info("✅ VirusTotal API connection successful")
                    return True
                else:
                    logger.error("❌ VirusTotal API connection failed")
                    return False
        except VirusTotalAPIError as e:
            logger.error(f"❌ VirusTotal API error: {e.message}")
            return False
        except Exception as e:
            logger.error(f"❌ Connection test failed: {e}")
            return False
    
    async def test_ip_analysis(self, ip="8.8.8.8"):
        """Test IP address analysis"""
        logger.info(f"Testing IP analysis for {ip}...")
        
        try:
            async with self.client:
                result = await self.client.get_ip_info(ip)
                
                if result and 'data' in result:
                    attributes = result['data'].get('attributes', {})
                    stats = attributes.get('last_analysis_stats', {})
                    
                    logger.info(f"✅ IP analysis successful for {ip}")
                    logger.info(f"   Country: {attributes.get('country', 'Unknown')}")
                    logger.info(f"   AS Owner: {attributes.get('as_owner', 'Unknown')}")
                    logger.info(f"   Reputation: {attributes.get('reputation', 'Unknown')}")
                    
                    if stats:
                        logger.info(f"   Detection stats: {stats.get('malicious', 0)} malicious, {stats.get('harmless', 0)} harmless")
                    
                    return True
                else:
                    logger.error("❌ Invalid response format")
                    return False
                    
        except VirusTotalAPIError as e:
            logger.error(f"❌ VirusTotal API error: {e.message}")
            return False
        except Exception as e:
            logger.error(f"❌ IP analysis failed: {e}")
            return False
    
    async def test_domain_analysis(self, domain="google.com"):
        """Test domain analysis"""
        logger.info(f"Testing domain analysis for {domain}...")
        
        try:
            async with self.client:
                result = await self.client.get_domain_info(domain)
                
                if result and 'data' in result:
                    attributes = result['data'].get('attributes', {})
                    stats = attributes.get('last_analysis_stats', {})
                    
                    logger.info(f"✅ Domain analysis successful for {domain}")
                    logger.info(f"   Reputation: {attributes.get('reputation', 'Unknown')}")
                    logger.info(f"   Categories: {attributes.get('categories', {})}")
                    
                    if stats:
                        logger.info(f"   Detection stats: {stats.get('malicious', 0)} malicious, {stats.get('harmless', 0)} harmless")
                    
                    return True
                else:
                    logger.error("❌ Invalid response format")
                    return False
                    
        except VirusTotalAPIError as e:
            logger.error(f"❌ VirusTotal API error: {e.message}")
            return False
        except Exception as e:
            logger.error(f"❌ Domain analysis failed: {e}")
            return False
    
    async def test_url_analysis(self, url="https://www.google.com"):
        """Test URL analysis"""
        logger.info(f"Testing URL analysis for {url}...")
        
        try:
            async with self.client:
                # Submit URL for analysis
                submit_result = await self.client.analyze_url(url)
                
                if submit_result and 'data' in submit_result:
                    logger.info(f"✅ URL submission successful for {url}")
                    logger.info(f"   Analysis ID: {submit_result['data'].get('id', 'Unknown')}")
                    return True
                else:
                    logger.error("❌ Invalid submission response")
                    return False
                    
        except VirusTotalAPIError as e:
            logger.error(f"❌ VirusTotal API error: {e.message}")
            return False
        except Exception as e:
            logger.error(f"❌ URL analysis failed: {e}")
            return False
    
    async def test_hash_lookup(self, file_hash="44d88612fea8a8f36de82e1278abb02f"):
        """Test file hash lookup (using a known hash)"""
        logger.info(f"Testing hash lookup for {file_hash}...")
        
        try:
            async with self.client:
                result = await self.client.get_file_analysis(file_hash)
                
                if result and 'data' in result:
                    attributes = result['data'].get('attributes', {})
                    stats = attributes.get('last_analysis_stats', {})
                    
                    logger.info(f"✅ Hash lookup successful for {file_hash}")
                    logger.info(f"   SHA256: {attributes.get('sha256', 'Unknown')}")
                    logger.info(f"   File size: {attributes.get('size', 'Unknown')} bytes")
                    logger.info(f"   File type: {attributes.get('type_description', 'Unknown')}")
                    
                    if stats:
                        logger.info(f"   Detection stats: {stats.get('malicious', 0)} malicious, {stats.get('harmless', 0)} harmless")
                    
                    return True
                else:
                    logger.error("❌ Invalid response format")
                    return False
                    
        except VirusTotalAPIError as e:
            if e.status_code == 404:
                logger.info(f"ℹ️  Hash not found in VirusTotal database (this is normal for test hashes)")
                return True
            logger.error(f"❌ VirusTotal API error: {e.message}")
            return False
        except Exception as e:
            logger.error(f"❌ Hash lookup failed: {e}")
            return False
    
    async def test_search(self, query="type:ip-address country:US"):
        """Test VirusTotal search functionality"""
        logger.info(f"Testing search with query: {query}")
        
        try:
            async with self.client:
                result = await self.client.search(query, limit=5)
                
                if result and 'data' in result:
                    data_count = len(result['data'])
                    logger.info(f"✅ Search successful, returned {data_count} results")
                    
                    for i, item in enumerate(result['data'][:3]):  # Show first 3 results
                        logger.info(f"   Result {i+1}: {item.get('type')} - {item.get('id')}")
                    
                    return True
                else:
                    logger.error("❌ Invalid search response")
                    return False
                    
        except VirusTotalAPIError as e:
            logger.error(f"❌ VirusTotal API error: {e.message}")
            return False
        except Exception as e:
            logger.error(f"❌ Search failed: {e}")
            return False
    
    async def test_rate_limiting(self):
        """Test rate limiting functionality"""
        logger.info("Testing rate limiting...")
        
        try:
            stats = self.client.get_stats()
            logger.info(f"✅ Rate limiting stats retrieved")
            logger.info(f"   Total requests: {stats.get('total_requests', 0)}")
            logger.info(f"   Daily requests: {stats.get('daily_requests', 0)}")
            
            rate_limit = stats.get('rate_limit', {})
            if rate_limit:
                logger.info(f"   Rate limit: {rate_limit.get('requests_per_minute', 0)} req/min")
                logger.info(f"   Daily usage: {rate_limit.get('daily_usage_percentage', 0):.2f}%")
            
            return True
            
        except Exception as e:
            logger.error(f"❌ Rate limiting test failed: {e}")
            return False
    
    async def run_all_tests(self):
        """Run all tests"""
        logger.info("🧪 Starting VirusTotal Integration Tests")
        logger.info("=" * 60)
        
        await self.setup()
        
        tests = [
            ("Connection Test", self.test_connection),
            ("IP Analysis", self.test_ip_analysis),
            ("Domain Analysis", self.test_domain_analysis),
            ("URL Analysis", self.test_url_analysis),
            ("Hash Lookup", self.test_hash_lookup),
            ("Search", self.test_search),
            ("Rate Limiting", self.test_rate_limiting),
        ]
        
        results = []
        
        for test_name, test_func in tests:
            logger.info(f"\n🔍 Running {test_name}...")
            try:
                result = await test_func()
                results.append((test_name, result))
                if result:
                    logger.info(f"✅ {test_name} PASSED")
                else:
                    logger.error(f"❌ {test_name} FAILED")
            except Exception as e:
                logger.error(f"❌ {test_name} ERROR: {e}")
                results.append((test_name, False))
            
            # Add delay between tests to respect rate limits
            await asyncio.sleep(1)
        
        # Summary
        logger.info("\n" + "=" * 60)
        logger.info("📊 TEST SUMMARY")
        logger.info("=" * 60)
        
        passed = sum(1 for _, result in results if result)
        total = len(results)
        
        for test_name, result in results:
            status = "✅ PASS" if result else "❌ FAIL"
            logger.info(f"{status} - {test_name}")
        
        logger.info("-" * 60)
        logger.info(f"Results: {passed}/{total} tests passed ({(passed/total)*100:.1f}%)")
        
        if passed == total:
            logger.info("🎉 All tests passed! VirusTotal integration is working correctly.")
        else:
            logger.warning(f"⚠️  {total - passed} tests failed. Check the logs above for details.")
        
        return passed == total

async def main():
    """Main test runner"""
    tester = VirusTotalTester()
    
    try:
        success = await tester.run_all_tests()
        return 0 if success else 1
    except Exception as e:
        logger.error(f"Test runner failed: {e}")
        return 1

if __name__ == "__main__":
    exit_code = asyncio.run(main())
    sys.exit(exit_code)