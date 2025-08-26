#!/usr/bin/env python3
"""
URL Analysis Script for OSINT
Based on TFM requirements for URL intelligence gathering
"""

import sys
import json
import requests
import argparse
import base64
from urllib.parse import urlparse
from typing import Dict, Any
import os

def analyze_url(url: str) -> Dict[str, Any]:
    """
    Comprehensive URL analysis using multiple sources
    """
    results = {
        'url': url,
        'url_info': parse_url(url),
        'reputation': get_reputation(url),
        'threat_intelligence': get_threat_intelligence(url),
        'content_analysis': analyze_content(url),
        'redirects': check_redirects(url)
    }
    
    # Calculate threat score
    results['threat_score'] = calculate_threat_score(results)
    
    return results

def parse_url(url: str) -> Dict[str, Any]:
    """
    Parse URL components
    """
    try:
        parsed = urlparse(url)
        return {
            'scheme': parsed.scheme,
            'hostname': parsed.hostname,
            'port': parsed.port,
            'path': parsed.path,
            'query': parsed.query,
            'fragment': parsed.fragment
        }
    except Exception as e:
        return {'error': str(e)}

def get_reputation(url: str) -> Dict[str, Any]:
    """
    Check URL reputation using URLVoid (if API key available)
    """
    urlvoid_key = os.getenv('URLVOID_API_KEY')
    urlvoid_id = os.getenv('URLVOID_IDENTIFIER')
    
    if not urlvoid_key or not urlvoid_id:
        return {'error': 'URLVoid API credentials not configured'}
    
    try:
        # Extract domain from URL
        domain = urlparse(url).hostname
        if not domain:
            return {'error': 'Could not extract domain from URL'}
        
        response = requests.get(
            f'http://api.urlvoid.com/api1000/{urlvoid_id}/host/{domain}',
            params={'key': urlvoid_key},
            timeout=15
        )
        
        if response.status_code == 200:
            data = response.json()
            detections = data.get('detections', {})
            engines = detections.get('engines', [])
            
            detected = sum(1 for engine in engines if engine.get('detected', False))
            total = len(engines)
            
            return {
                'detected_engines': detected,
                'total_engines': total,
                'detection_ratio': (detected / total * 100) if total > 0 else 0,
                'engines': engines
            }
        else:
            return {'error': f'URLVoid API request failed: {response.status_code}'}
            
    except Exception as e:
        return {'error': str(e)}

def get_threat_intelligence(url: str) -> Dict[str, Any]:
    """
    Get threat intelligence using VirusTotal (if API key available)
    """
    api_key = os.getenv('VIRUSTOTAL_API_KEY')
    
    if not api_key:
        return {'error': 'VirusTotal API key not configured'}
    
    try:
        # Encode URL for VirusTotal API
        url_id = base64.urlsafe_b64encode(url.encode()).decode().strip('=')
        
        headers = {'X-Apikey': api_key}
        
        response = requests.get(
            f'https://www.virustotal.com/api/v3/urls/{url_id}',
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
                'title': data.get('title', ''),
                'last_final_url': data.get('last_final_url', url),
                'categories': data.get('categories', {}),
                'threat_names': data.get('threat_names', [])
            }
        elif response.status_code == 404:
            return {'error': 'URL not found in VirusTotal database'}
        else:
            return {'error': f'VirusTotal API request failed: {response.status_code}'}
            
    except Exception as e:
        return {'error': str(e)}

def analyze_content(url: str) -> Dict[str, Any]:
    """
    Basic content analysis of the URL
    """
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        
        response = requests.get(url, headers=headers, timeout=10, allow_redirects=True)
        
        content_info = {
            'status_code': response.status_code,
            'content_type': response.headers.get('Content-Type', ''),
            'content_length': len(response.content),
            'server': response.headers.get('Server', ''),
            'final_url': response.url
        }
        
        # Basic content analysis for HTML
        if 'text/html' in content_info['content_type']:
            from bs4 import BeautifulSoup
            try:
                soup = BeautifulSoup(response.content, 'html.parser')
                content_info.update({
                    'title': soup.title.string if soup.title else '',
                    'meta_description': '',
                    'external_links': len([link for link in soup.find_all('a', href=True) 
                                         if not link['href'].startswith('#')]),
                    'forms': len(soup.find_all('form')),
                    'scripts': len(soup.find_all('script'))
                })
                
                # Get meta description
                meta_desc = soup.find('meta', attrs={'name': 'description'})
                if meta_desc:
                    content_info['meta_description'] = meta_desc.get('content', '')
                
            except:
                pass
        
        return content_info
        
    except Exception as e:
        return {'error': str(e)}

def check_redirects(url: str) -> Dict[str, Any]:
    """
    Check URL redirects
    """
    try:
        headers = {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        }
        
        response = requests.get(url, headers=headers, timeout=10, allow_redirects=False)
        
        redirect_chain = []
        current_url = url
        
        while response.status_code in [301, 302, 303, 307, 308]:
            redirect_chain.append({
                'from': current_url,
                'to': response.headers.get('Location', ''),
                'status_code': response.status_code
            })
            
            current_url = response.headers.get('Location', '')
            if not current_url or len(redirect_chain) > 10:  # Prevent infinite loops
                break
                
            response = requests.get(current_url, headers=headers, timeout=10, allow_redirects=False)
        
        return {
            'redirect_count': len(redirect_chain),
            'redirect_chain': redirect_chain,
            'final_url': current_url
        }
        
    except Exception as e:
        return {'error': str(e)}

def calculate_threat_score(results: Dict[str, Any]) -> Dict[str, Any]:
    """
    Calculate overall threat score based on analysis results
    """
    score = 0
    factors = []
    
    # VirusTotal detections
    threat_intel = results.get('threat_intelligence', {})
    if 'malicious' in threat_intel and 'suspicious' in threat_intel:
        malicious = threat_intel['malicious']
        suspicious = threat_intel['suspicious']
        
        if malicious > 5:
            score += 40
            factors.append(f'{malicious} malicious detections')
        elif malicious > 0:
            score += 20
            factors.append(f'{malicious} malicious detections')
        
        if suspicious > 3:
            score += 15
            factors.append(f'{suspicious} suspicious detections')
    
    # URLVoid detections
    reputation = results.get('reputation', {})
    if 'detection_ratio' in reputation:
        ratio = reputation['detection_ratio']
        if ratio > 30:
            score += 25
            factors.append(f'High URLVoid detection ratio: {ratio:.1f}%')
        elif ratio > 10:
            score += 10
            factors.append(f'Medium URLVoid detection ratio: {ratio:.1f}%')
    
    # Multiple redirects (suspicious)
    redirects = results.get('redirects', {})
    if 'redirect_count' in redirects:
        count = redirects['redirect_count']
        if count > 3:
            score += 10
            factors.append(f'Multiple redirects ({count})')
    
    # Content analysis
    content = results.get('content_analysis', {})
    if 'status_code' in content:
        if content['status_code'] != 200:
            score += 5
            factors.append(f'Non-200 status code: {content["status_code"]}')
    
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
    parser = argparse.ArgumentParser(description='URL Analysis Script')
    parser.add_argument('url', help='URL to analyze')
    parser.add_argument('--format', choices=['json', 'text'], default='json')
    parser.add_argument('--info', action='store_true', help='Show script information')
    
    args = parser.parse_args()
    
    if args.info:
        info = {
            'name': 'url_analysis.py',
            'target_type': 'url',
            'description': 'OSINT analysis for URL targets',
            'version': '1.0.0',
            'author': 'OSINT Team',
            'supported_apis': ['virustotal', 'urlvoid']
        }
        print(json.dumps(info, indent=2))
        return
    
    try:
        results = analyze_url(args.url)
        
        if args.format == 'json':
            print(json.dumps(results, indent=2, default=str))
        else:
            # Text format output
            print(f"URL Analysis Results for {args.url}")
            print("=" * 50)
            for key, value in results.items():
                print(f"{key.replace('_', ' ').title()}: {value}")
    
    except Exception as e:
        error_result = {'error': str(e), 'url': args.url}
        if args.format == 'json':
            print(json.dumps(error_result))
        else:
            print(f"Error analyzing {args.url}: {e}")
        sys.exit(1)

if __name__ == '__main__':
    main()