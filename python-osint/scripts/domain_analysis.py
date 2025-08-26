#!/usr/bin/env python3
"""
Domain Analysis Script for OSINT
Based on TFM requirements for domain intelligence gathering
"""

import sys
import json
import requests
import socket
import argparse
import dns.resolver
import whois
from typing import Dict, Any, List
import os
from datetime import datetime

def analyze_domain(domain: str) -> Dict[str, Any]:
    """
    Comprehensive domain analysis using multiple sources
    """
    results = {
        'domain': domain,
        'dns_records': get_dns_records(domain),
        'whois_info': get_whois_info(domain),
        'reputation': get_reputation(domain),
        'subdomains': find_subdomains(domain),
        'ssl_info': get_ssl_info(domain),
        'threat_intelligence': get_threat_intelligence(domain)
    }
    
    # Calculate threat score
    results['threat_score'] = calculate_threat_score(results)
    
    return results

def get_dns_records(domain: str) -> Dict[str, Any]:
    """
    Get comprehensive DNS records for domain
    """
    records = {}
    
    record_types = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SOA']
    
    for record_type in record_types:
        try:
            answers = dns.resolver.resolve(domain, record_type)
            records[record_type] = []
            
            for answer in answers:
                if record_type == 'MX':
                    records[record_type].append({
                        'preference': answer.preference,
                        'exchange': str(answer.exchange)
                    })
                elif record_type == 'SOA':
                    records[record_type].append({
                        'mname': str(answer.mname),
                        'rname': str(answer.rname),
                        'serial': answer.serial
                    })
                else:
                    records[record_type].append(str(answer))
                    
        except Exception as e:
            records[record_type] = {'error': str(e)}
    
    return records

def get_whois_info(domain: str) -> Dict[str, Any]:
    """
    Get WHOIS information for domain
    """
    try:
        w = whois.whois(domain)
        
        # Convert datetime objects to strings for JSON serialization
        creation_date = w.creation_date
        if isinstance(creation_date, list):
            creation_date = creation_date[0] if creation_date else None
        if creation_date:
            creation_date = creation_date.isoformat() if isinstance(creation_date, datetime) else str(creation_date)
        
        expiration_date = w.expiration_date
        if isinstance(expiration_date, list):
            expiration_date = expiration_date[0] if expiration_date else None
        if expiration_date:
            expiration_date = expiration_date.isoformat() if isinstance(expiration_date, datetime) else str(expiration_date)
        
        return {
            'registrar': w.registrar,
            'creation_date': creation_date,
            'expiration_date': expiration_date,
            'name_servers': w.name_servers if isinstance(w.name_servers, list) else [w.name_servers] if w.name_servers else [],
            'status': w.status if isinstance(w.status, list) else [w.status] if w.status else [],
            'emails': w.emails if isinstance(w.emails, list) else [w.emails] if w.emails else [],
            'country': w.country,
            'org': w.org
        }
        
    except Exception as e:
        return {'error': str(e)}

def get_reputation(domain: str) -> Dict[str, Any]:
    """
    Check domain reputation using multiple sources
    """
    reputation = {}
    
    # URLVoid check (if API key available)
    urlvoid_key = os.getenv('URLVOID_API_KEY')
    urlvoid_id = os.getenv('URLVOID_IDENTIFIER')
    
    if urlvoid_key and urlvoid_id:
        try:
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
                
                reputation['urlvoid'] = {
                    'detected_engines': detected,
                    'total_engines': total,
                    'detection_ratio': (detected / total * 100) if total > 0 else 0,
                    'engines': engines
                }
            else:
                reputation['urlvoid'] = {'error': f'API request failed: {response.status_code}'}
                
        except Exception as e:
            reputation['urlvoid'] = {'error': str(e)}
    
    return reputation

def find_subdomains(domain: str) -> Dict[str, Any]:
    """
    Find subdomains using common prefixes and DNS enumeration
    """
    common_subdomains = [
        'www', 'mail', 'ftp', 'admin', 'blog', 'dev', 'test', 'api',
        'staging', 'secure', 'shop', 'news', 'support', 'forum',
        'cdn', 'static', 'assets', 'img', 'images', 'video'
    ]
    
    found_subdomains = []
    
    for sub in common_subdomains:
        subdomain = f"{sub}.{domain}"
        try:
            answers = dns.resolver.resolve(subdomain, 'A')
            ips = [str(answer) for answer in answers]
            found_subdomains.append({
                'subdomain': subdomain,
                'ips': ips
            })
        except:
            continue
    
    return {
        'found': len(found_subdomains),
        'subdomains': found_subdomains
    }

def get_ssl_info(domain: str) -> Dict[str, Any]:
    """
    Get SSL certificate information
    """
    try:
        import ssl
        import socket
        from datetime import datetime
        
        context = ssl.create_default_context()
        
        with socket.create_connection((domain, 443), timeout=10) as sock:
            with context.wrap_socket(sock, server_hostname=domain) as ssock:
                cert = ssock.getpeercert()
                
                return {
                    'subject': dict(x[0] for x in cert.get('subject', [])),
                    'issuer': dict(x[0] for x in cert.get('issuer', [])),
                    'version': cert.get('version'),
                    'serial_number': cert.get('serialNumber'),
                    'not_before': cert.get('notBefore'),
                    'not_after': cert.get('notAfter'),
                    'san': cert.get('subjectAltName', [])
                }
                
    except Exception as e:
        return {'error': str(e)}

def get_threat_intelligence(domain: str) -> Dict[str, Any]:
    """
    Get threat intelligence using VirusTotal (if API key available)
    """
    api_key = os.getenv('VIRUSTOTAL_API_KEY')
    
    if not api_key:
        return {'error': 'VirusTotal API key not configured'}
    
    try:
        headers = {'X-Apikey': api_key}
        
        response = requests.get(
            f'https://www.virustotal.com/api/v3/domains/{domain}',
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
                'categories': data.get('categories', {}),
                'creation_date': data.get('creation_date'),
                'registrar': data.get('registrar')
            }
        else:
            return {'error': f'VirusTotal API request failed: {response.status_code}'}
            
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
    if 'urlvoid' in reputation and 'detection_ratio' in reputation['urlvoid']:
        ratio = reputation['urlvoid']['detection_ratio']
        if ratio > 30:
            score += 25
            factors.append(f'High URLVoid detection ratio: {ratio:.1f}%')
        elif ratio > 10:
            score += 10
            factors.append(f'Medium URLVoid detection ratio: {ratio:.1f}%')
    
    # Recent domain (potential indicator)
    whois_info = results.get('whois_info', {})
    if 'creation_date' in whois_info and whois_info['creation_date']:
        try:
            creation = datetime.fromisoformat(whois_info['creation_date'].replace('Z', '+00:00'))
            days_old = (datetime.now() - creation.replace(tzinfo=None)).days
            
            if days_old < 30:
                score += 15
                factors.append(f'Very recent domain ({days_old} days old)')
            elif days_old < 90:
                score += 5
                factors.append(f'Recent domain ({days_old} days old)')
        except:
            pass
    
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
    parser = argparse.ArgumentParser(description='Domain Analysis Script')
    parser.add_argument('domain', help='Domain to analyze')
    parser.add_argument('--format', choices=['json', 'text'], default='json')
    
    args = parser.parse_args()
    
    try:
        results = analyze_domain(args.domain)
        
        if args.format == 'json':
            print(json.dumps(results, indent=2, default=str))
        else:
            # Text format output
            print(f"Domain Analysis Results for {args.domain}")
            print("=" * 40)
            for key, value in results.items():
                print(f"{key.replace('_', ' ').title()}: {value}")
    
    except Exception as e:
        error_result = {'error': str(e), 'domain': args.domain}
        if args.format == 'json':
            print(json.dumps(error_result))
        else:
            print(f"Error analyzing {args.domain}: {e}")
        sys.exit(1)

if __name__ == '__main__':
    main()