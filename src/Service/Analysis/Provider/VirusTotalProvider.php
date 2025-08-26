<?php

namespace App\Service\Analysis\Provider;

class VirusTotalProvider extends AbstractApiProvider
{
    public function analyze(string $targetType, string $targetValue): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return $this->formatResult('VirusTotal', [], 'error', 'API key not configured');
        }
        
        return match ($targetType) {
            'ip' => $this->analyzeIp($targetValue),
            'domain' => $this->analyzeDomain($targetValue),
            'url' => $this->analyzeUrl($targetValue),
            'hash' => $this->analyzeHash($targetValue),
            default => $this->formatResult('VirusTotal', [], 'error', 'Unsupported target type')
        };
    }
    
    public function getSupportedTypes(): array
    {
        return ['ip', 'domain', 'url', 'hash'];
    }
    
    private function analyzeIp(string $ip): array
    {
        $result = $this->makeRequest('GET', "/api/v3/ip_addresses/{$ip}", [
            'headers' => [
                'X-Apikey' => $this->getApiKey()
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('VirusTotal', [], 'error', $result['error']);
        }
        
        $data = $result['data']['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $stats = $attributes['last_analysis_stats'] ?? [];
        
        return $this->formatResult('VirusTotal', [
            'ip' => $ip,
            'country' => $attributes['country'] ?? 'Unknown',
            'asn' => $attributes['asn'] ?? 'Unknown',
            'as_owner' => $attributes['as_owner'] ?? 'Unknown',
            'reputation' => $attributes['reputation'] ?? 0,
            'malicious' => $stats['malicious'] ?? 0,
            'suspicious' => $stats['suspicious'] ?? 0,
            'clean' => $stats['harmless'] ?? 0,
            'undetected' => $stats['undetected'] ?? 0,
            'last_analysis_date' => $attributes['last_analysis_date'] ?? null,
            'threat_level' => $this->calculateThreatLevel($stats),
            'raw_response' => $attributes
        ]);
    }
    
    private function analyzeDomain(string $domain): array
    {
        $result = $this->makeRequest('GET', "/api/v3/domains/{$domain}", [
            'headers' => [
                'X-Apikey' => $this->getApiKey()
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('VirusTotal', [], 'error', $result['error']);
        }
        
        $data = $result['data']['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $stats = $attributes['last_analysis_stats'] ?? [];
        
        return $this->formatResult('VirusTotal', [
            'domain' => $domain,
            'registrar' => $attributes['registrar'] ?? 'Unknown',
            'creation_date' => $attributes['creation_date'] ?? null,
            'reputation' => $attributes['reputation'] ?? 0,
            'malicious' => $stats['malicious'] ?? 0,
            'suspicious' => $stats['suspicious'] ?? 0,
            'clean' => $stats['harmless'] ?? 0,
            'undetected' => $stats['undetected'] ?? 0,
            'categories' => $attributes['categories'] ?? [],
            'threat_level' => $this->calculateThreatLevel($stats),
            'raw_response' => $attributes
        ]);
    }
    
    private function analyzeUrl(string $url): array
    {
        $urlId = base64_encode($url);
        $urlId = str_replace('=', '', $urlId);
        
        $result = $this->makeRequest('GET', "/api/v3/urls/{$urlId}", [
            'headers' => [
                'X-Apikey' => $this->getApiKey()
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('VirusTotal', [], 'error', $result['error']);
        }
        
        $data = $result['data']['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $stats = $attributes['last_analysis_stats'] ?? [];
        
        return $this->formatResult('VirusTotal', [
            'url' => $url,
            'title' => $attributes['title'] ?? 'Unknown',
            'final_url' => $attributes['last_final_url'] ?? $url,
            'malicious' => $stats['malicious'] ?? 0,
            'suspicious' => $stats['suspicious'] ?? 0,
            'clean' => $stats['harmless'] ?? 0,
            'undetected' => $stats['undetected'] ?? 0,
            'categories' => $attributes['categories'] ?? [],
            'threat_level' => $this->calculateThreatLevel($stats),
            'raw_response' => $attributes
        ]);
    }
    
    private function analyzeHash(string $hash): array
    {
        $result = $this->makeRequest('GET', "/api/v3/files/{$hash}", [
            'headers' => [
                'X-Apikey' => $this->getApiKey()
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('VirusTotal', [], 'error', $result['error']);
        }
        
        $data = $result['data']['data'] ?? [];
        $attributes = $data['attributes'] ?? [];
        $stats = $attributes['last_analysis_stats'] ?? [];
        
        return $this->formatResult('VirusTotal', [
            'hash' => $hash,
            'md5' => $attributes['md5'] ?? '',
            'sha1' => $attributes['sha1'] ?? '',
            'sha256' => $attributes['sha256'] ?? '',
            'file_type' => $attributes['type_description'] ?? 'Unknown',
            'size' => $attributes['size'] ?? 0,
            'malicious' => $stats['malicious'] ?? 0,
            'suspicious' => $stats['suspicious'] ?? 0,
            'clean' => $stats['harmless'] ?? 0,
            'undetected' => $stats['undetected'] ?? 0,
            'threat_level' => $this->calculateThreatLevel($stats),
            'raw_response' => $attributes
        ]);
    }
    
    private function calculateThreatLevel(array $stats): string
    {
        $malicious = $stats['malicious'] ?? 0;
        $suspicious = $stats['suspicious'] ?? 0;
        $total = array_sum($stats);
        
        if ($total === 0) {
            return 'unknown';
        }
        
        $maliciousRatio = ($malicious + $suspicious) / $total;
        
        if ($maliciousRatio >= 0.3) {
            return 'high';
        } elseif ($maliciousRatio >= 0.1) {
            return 'medium';
        } elseif ($maliciousRatio > 0) {
            return 'low';
        }
        
        return 'clean';
    }
}