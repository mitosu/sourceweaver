<?php

namespace App\Service\Analysis\Provider;

class URLVoidProvider extends AbstractApiProvider
{
    public function analyze(string $targetType, string $targetValue): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return $this->formatResult('URLVoid', [], 'error', 'API key not configured');
        }
        
        return match ($targetType) {
            'url', 'domain' => $this->analyzeDomain($this->extractDomain($targetValue)),
            'ip' => $this->analyzeIp($targetValue),
            default => $this->formatResult('URLVoid', [], 'error', 'Unsupported target type')
        };
    }
    
    public function getSupportedTypes(): array
    {
        return ['url', 'domain', 'ip'];
    }
    
    private function analyzeDomain(string $domain): array
    {
        $identifier = $this->config['identifier'] ?? '';
        
        $result = $this->makeRequest('GET', "/api1000/{$identifier}/host/{$domain}", [
            'query' => [
                'key' => $this->getApiKey()
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('URLVoid', [], 'error', $result['error']);
        }
        
        $data = $result['data'] ?? [];
        $detections = $data['detections'] ?? [];
        $details = $data['details'] ?? [];
        
        $engines = $detections['engines'] ?? [];
        $detected = 0;
        $total = 0;
        
        foreach ($engines as $engine) {
            $total++;
            if (isset($engine['detected']) && $engine['detected']) {
                $detected++;
            }
        }
        
        $threatLevel = $this->calculateThreatLevel($detected, $total);
        
        return $this->formatResult('URLVoid', [
            'domain' => $domain,
            'detected_engines' => $detected,
            'total_engines' => $total,
            'detection_ratio' => $total > 0 ? round(($detected / $total) * 100, 2) : 0,
            'server_ip' => $details['ip'] ?? 'Unknown',
            'server_location' => $details['city_name'] ?? 'Unknown',
            'asn' => $details['asn'] ?? 'Unknown',
            'threat_level' => $threatLevel,
            'engines' => $engines,
            'raw_response' => $data
        ]);
    }
    
    private function analyzeIp(string $ip): array
    {
        $identifier = $this->config['identifier'] ?? '';
        
        $result = $this->makeRequest('GET', "/api1000/{$identifier}/ip/{$ip}", [
            'query' => [
                'key' => $this->getApiKey()
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('URLVoid', [], 'error', $result['error']);
        }
        
        $data = $result['data'] ?? [];
        $detections = $data['detections'] ?? [];
        $details = $data['details'] ?? [];
        
        $engines = $detections['engines'] ?? [];
        $detected = 0;
        $total = 0;
        
        foreach ($engines as $engine) {
            $total++;
            if (isset($engine['detected']) && $engine['detected']) {
                $detected++;
            }
        }
        
        $threatLevel = $this->calculateThreatLevel($detected, $total);
        
        return $this->formatResult('URLVoid', [
            'ip' => $ip,
            'detected_engines' => $detected,
            'total_engines' => $total,
            'detection_ratio' => $total > 0 ? round(($detected / $total) * 100, 2) : 0,
            'location' => $details['city_name'] ?? 'Unknown',
            'country' => $details['country_name'] ?? 'Unknown',
            'asn' => $details['asn'] ?? 'Unknown',
            'reverse_dns' => $details['reverse_dns'] ?? 'Unknown',
            'threat_level' => $threatLevel,
            'engines' => $engines,
            'raw_response' => $data
        ]);
    }
    
    private function extractDomain(string $urlOrDomain): string
    {
        if (filter_var($urlOrDomain, FILTER_VALIDATE_URL)) {
            $parsed = parse_url($urlOrDomain);
            return $parsed['host'] ?? $urlOrDomain;
        }
        
        return $urlOrDomain;
    }
    
    private function calculateThreatLevel(int $detected, int $total): string
    {
        if ($total === 0) {
            return 'unknown';
        }
        
        $ratio = $detected / $total;
        
        if ($ratio >= 0.3) {
            return 'high';
        } elseif ($ratio >= 0.1) {
            return 'medium';
        } elseif ($ratio > 0) {
            return 'low';
        }
        
        return 'clean';
    }
}