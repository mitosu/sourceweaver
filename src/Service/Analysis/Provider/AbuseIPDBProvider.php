<?php

namespace App\Service\Analysis\Provider;

class AbuseIPDBProvider extends AbstractApiProvider
{
    public function analyze(string $targetType, string $targetValue): array
    {
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            return $this->formatResult('AbuseIPDB', [], 'error', 'API key not configured');
        }
        
        if ($targetType !== 'ip') {
            return $this->formatResult('AbuseIPDB', [], 'error', 'Only IP addresses are supported');
        }
        
        return $this->analyzeIp($targetValue);
    }
    
    public function getSupportedTypes(): array
    {
        return ['ip'];
    }
    
    private function analyzeIp(string $ip): array
    {
        $result = $this->makeRequest('GET', '/api/v2/check', [
            'headers' => [
                'Key' => $this->getApiKey(),
                'Accept' => 'application/json'
            ],
            'query' => [
                'ipAddress' => $ip,
                'maxAgeInDays' => 90,
                'verbose' => true
            ]
        ]);
        
        if ($result['status'] === 'error') {
            return $this->formatResult('AbuseIPDB', [], 'error', $result['error']);
        }
        
        $data = $result['data']['data'] ?? [];
        
        $abuseConfidence = $data['abuseConfidencePercentage'] ?? 0;
        $threatLevel = $this->calculateThreatLevel($abuseConfidence);
        
        return $this->formatResult('AbuseIPDB', [
            'ip' => $ip,
            'abuse_confidence' => $abuseConfidence,
            'country_code' => $data['countryCode'] ?? 'Unknown',
            'country_name' => $data['countryName'] ?? 'Unknown',
            'usage_type' => $data['usageType'] ?? 'Unknown',
            'isp' => $data['isp'] ?? 'Unknown',
            'domain' => $data['domain'] ?? 'Unknown',
            'is_public' => $data['isPublic'] ?? true,
            'is_whitelisted' => $data['isWhitelisted'] ?? false,
            'total_reports' => $data['totalReports'] ?? 0,
            'num_distinct_users' => $data['numDistinctUsers'] ?? 0,
            'last_reported_at' => $data['lastReportedAt'] ?? null,
            'threat_level' => $threatLevel,
            'raw_response' => $data
        ]);
    }
    
    private function calculateThreatLevel(int $abuseConfidence): string
    {
        if ($abuseConfidence >= 75) {
            return 'high';
        } elseif ($abuseConfidence >= 25) {
            return 'medium';
        } elseif ($abuseConfidence > 0) {
            return 'low';
        }
        
        return 'clean';
    }
}