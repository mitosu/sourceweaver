<?php

namespace App\Service\Analysis\Provider;

use Psr\Log\LoggerInterface;

class FastApiProvider extends AbstractApiProvider
{
    private string $fastApiUrl;
    private LoggerInterface $logger;
    
    public function __construct(
        \Symfony\Contracts\HttpClient\HttpClientInterface $httpClient,
        \App\Entity\ApiConfiguration $apiConfiguration,
        LoggerInterface $logger
    ) {
        parent::__construct($httpClient, $apiConfiguration);
        $this->logger = $logger;
    }
    
    public function analyze(string $targetType, string $targetValue): array
    {
        $this->fastApiUrl = $this->config['fast_api_url'] ?? 'http://python-osint:8001';
        
        $requestData = [
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'config' => (object) $this->getApiKeys(), // Force to object for JSON serialization
            'timeout' => 120
        ];
        
        $this->logger->info('Sending request to FastAPI', [
            'url' => $this->fastApiUrl,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'request_data' => $requestData
        ]);
        
        try {
            
            $response = $this->httpClient->request('POST', $this->fastApiUrl . '/analyze', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $requestData,
                'timeout' => 150
            ]);
            
            $data = $response->toArray();
            
            $this->logger->info('FastAPI response received', [
                'status' => $data['status'] ?? 'unknown',
                'execution_time' => $data['execution_time'] ?? 0
            ]);
            
            if ($data['status'] === 'error') {
                return $this->formatResult('FastAPI-Python', [], 'error', $data['error'] ?? 'Unknown error');
            }
            
            // Add metadata about the analysis
            $analysisData = $data['data'] ?? [];
            $analysisData['_metadata'] = [
                'provider' => 'FastAPI-Python',
                'execution_time' => $data['execution_time'] ?? 0,
                'timestamp' => $data['timestamp'] ?? date('Y-m-d H:i:s'),
                'python_scripts' => true
            ];
            
            return $this->formatResult('FastAPI-Python', $analysisData, 'success');
            
        } catch (\Exception $e) {
            $this->logger->error('FastAPI request failed', [
                'error' => $e->getMessage(),
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'request_data' => $requestData,
                'exception_class' => get_class($e)
            ]);
            
            return $this->formatResult('FastAPI-Python', [], 'error', 'Python microservice unavailable: ' . $e->getMessage());
        }
    }
    
    public function getSupportedTypes(): array
    {
        $this->fastApiUrl = $this->config['fast_api_url'] ?? 'http://python-osint:8001';
        
        // Query the FastAPI service for available scripts
        try {
            $response = $this->httpClient->request('GET', $this->fastApiUrl . '/scripts', [
                'timeout' => 10
            ]);
            $data = $response->toArray();
            
            $supportedTypes = [];
            if (is_array($data)) {
                foreach ($data as $script) {
                    if (isset($script['target_type'])) {
                        $supportedTypes[] = $script['target_type'];
                    }
                }
            }
            
            return array_unique($supportedTypes);
            
        } catch (\Exception $e) {
            $this->logger->warning('Could not query FastAPI for supported types', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback to default types
            return ['ip', 'domain', 'url', 'email', 'hash'];
        }
    }
    
    private function getApiKeys(): array
    {
        $apiKeys = [];
        
        // Extract API keys from configuration
        foreach ($this->config as $key => $value) {
            if (stripos($key, 'api_key') !== false || stripos($key, 'key') !== false) {
                $apiKeys[$key] = $value;
            }
        }
        
        return $apiKeys;
    }
    
    public function testConnection(): bool
    {
        $this->fastApiUrl = $this->config['fast_api_url'] ?? 'http://python-osint:8001';
        
        try {
            $response = $this->httpClient->request('GET', $this->fastApiUrl . '/health', [
                'timeout' => 10
            ]);
            
            $data = $response->toArray();
            return $data['status'] === 'healthy';
            
        } catch (\Exception $e) {
            $this->logger->error('FastAPI connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}