#!/usr/bin/env python3
"""
IP Analysis Script for OSINT
Based on TFM requirements for IP intelligence gathering
"""

import sys
import json
import requests
import socket
import argparse
from typing import Dict, Any
import os

def analyze_ip(ip_address: str) -> Dict[str, Any]:
    """
    Comprehensive IP analysis using multiple sources
    """
    results = {
        'ip': ip_address,
        'geolocation': get_geolocation(ip_address),
        'reputation': get_reputation(ip_address),
        'dns_info': get_dns_info(ip_address),
        'threat_intelligence': get_threat_intelligence(ip_address),
        'whois': get_whois_info(ip_address)
    }
    
    # Calculate threat score
    results['threat_score'] = calculate_threat_score(results)
    
    return results

def get_geolocation(ip: str) -> Dict[str, Any]:
    """
    Get geolocation information for IP
    """
    try:
        # Using ipapi.co (free tier)
        response = requests.get(f'https://ipapi.co/{ip}/json/', timeout=10)
        if response.status_code == 200:
            data = response.json()
            return {
                'country': data.get('country_name'),
                'country_code': data.get('country_code'),
                'city': data.get('city'),
                'region': data.get('region'),
                'latitude': data.get('latitude'),
                'longitude': data.get('longitude'),
                'isp': data.get('org'),
                'asn': data.get('asn')
            }
    except Exception as e:
        return {'error': str(e)}
    
    return {'error': 'Failed to get geolocation'}

def get_reputation(ip: str) -> Dict[str, Any]:
    """
    Check IP reputation using AbuseIPDB (if API key available)
    """
    api_key = os.getenv('ABUSEIPDB_API_KEY')
    
    if not api_key:
        return {'error': 'AbuseIPDB API key not configured'}
    
    try:
        headers = {
            'Key': api_key,
            'Accept': 'application/json'
        }
        
        params = {
            'ipAddress': ip,
            'maxAgeInDays': 90,
            'verbose': True
        }
        
        response = requests.get(
            'https://api.abuseipdb.com/api/v2/check',
            headers=headers,
            params=params,
            timeout=15
        )
        
        if response.status_code == 200:
            data = response.json()['data']
            return {
                'abuse_confidence': data.get('abuseConfidencePercentage', 0),
                'is_public': data.get('isPublic', True),
                'is_whitelisted': data.get('isWhitelisted', False),
                'country_code': data.get('countryCode'),
                'usage_type': data.get('usageType'),
                'isp': data.get('isp'),
                'total_reports': data.get('totalReports', 0),
                'last_reported': data.get('lastReportedAt')
            }
        else:
            return {'error': f'API request failed: {response.status_code}'}
            
    except Exception as e:
        return {'error': str(e)}

def get_dns_info(ip: str) -> Dict[str, Any]:
    """
    Get DNS information for IP
    """
    try:
        # Reverse DNS lookup
        hostname = socket.gethostbyaddr(ip)[0]
        return {'hostname': hostname}
    except:
        return {'hostname': None}

def get_threat_intelligence(ip: str) -> Dict[str, Any]:
    """
    Get threat intelligence using VirusTotal (if API key available)
    """
    api_key = os.getenv('VIRUSTOTAL_API_KEY')
    
    if not api_key:
        return {'error': 'VirusTotal API key not configured'}
    
    try:
        headers = {'X-Apikey': api_key}
        
        response = requests.get(
            f'https://www.virustotal.com/api/v3/ip_addresses/{ip}',
            headers=headers,
            timeout=15
        )
        
        if response.status_code == 200:
            data = response.json()['data']['attributes']
            stats = data.get('last_analysis_stats', {})
            
            return {
                'malicious': stats.get('malicious', 0),
                'suspicious': stats.get('suspicious', 0),
                'clean': stats.get('harmless', 0),
                'undetected': stats.get('undetected', 0),
                'reputation': data.get('reputation', 0),
                'country': data.get('country'),
                'asn': data.get('asn'),
                'as_owner': data.get('as_owner')
            }
        else:
            return {'error': f'VirusTotal API request failed: {response.status_code}'}
            
    except Exception as e:
        return {'error': str(e)}

def get_whois_info(ip: str) -> Dict[str, Any]:
    """
    Get WHOIS information (simplified)
    """
    try:
        import subprocess
        result = subprocess.run(['whois', ip], capture_output=True, text=True, timeout=30)
        
        if result.returncode == 0:
            # Parse basic info from whois output
            lines = result.stdout.split('\n')
            info = {}
            
            for line in lines:
                if 'NetName:' in line:
                    info['net_name'] = line.split(':', 1)[1].strip()
                elif 'Organization:' in line or 'OrgName:' in line:
                    info['organization'] = line.split(':', 1)[1].strip()
                elif 'Country:' in line:
                    info['country'] = line.split(':', 1)[1].strip()
            
            return info
        else:
            return {'error': 'WHOIS lookup failed'}
            
    except Exception as e:
        return {'error': str(e)}

def calculate_threat_score(results: Dict[str, Any]) -> Dict[str, Any]:
    """
    Calculate overall threat score based on analysis results
    """
    score = 0
    factors = []
    
    # AbuseIPDB reputation
    if 'abuse_confidence' in results.get('reputation', {}):
        confidence = results['reputation']['abuse_confidence']
        if confidence > 75:
            score += 40
            factors.append('High AbuseIPDB confidence')
        elif confidence > 25:
            score += 20
            factors.append('Medium AbuseIPDB confidence')
    
    # VirusTotal detections
    threat_intel = results.get('threat_intelligence', {})
    if 'malicious' in threat_intel and 'suspicious' in threat_intel:
        malicious = threat_intel['malicious']
        suspicious = threat_intel['suspicious']
        
        if malicious > 5:
            score += 30
            factors.append(f'{malicious} malicious detections')
        elif malicious > 0:
            score += 15
            factors.append(f'{malicious} malicious detections')
        
        if suspicious > 3:
            score += 10
            factors.append(f'{suspicious} suspicious detections')
    
    # Determine threat level
    if score >= 60:
        level = 'high'
    elif score >= 30:
        level = 'medium'
    elif score > 0:
        level = 'low'
    else:
        level = 'clean'
    
    return {
        'score': score,
        'level': level,
        'factors': factors
    }

def main():
    parser = argparse.ArgumentParser(description='IP Analysis Script')
    parser.add_argument('ip', help='IP address to analyze')
    parser.add_argument('--format', choices=['json', 'text'], default='json')
    
    args = parser.parse_args()
    
    try:
        results = analyze_ip(args.ip)
        
        if args.format == 'json':
            print(json.dumps(results, indent=2))
        else:
            # Text format output
            print(f"IP Analysis Results for {args.ip}")
            print("=" * 40)
            for key, value in results.items():
                print(f"{key.replace('_', ' ').title()}: {value}")
    
    except Exception as e:
        error_result = {'error': str(e), 'ip': args.ip}
        if args.format == 'json':
            print(json.dumps(error_result))
        else:
            print(f"Error analyzing {args.ip}: {e}")
        sys.exit(1)

if __name__ == '__main__':
    main()