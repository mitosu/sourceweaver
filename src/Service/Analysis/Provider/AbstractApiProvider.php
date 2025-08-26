<?php

namespace App\Service\Analysis\Provider;

use App\Entity\ApiConfiguration;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractApiProvider
{
    protected array $config = [];
    
    public function __construct(
        protected HttpClientInterface $httpClient,
        protected ApiConfiguration $apiConfiguration
    ) {
        $this->initializeConfig();
    }
    
    protected function initializeConfig(): void
    {
        foreach ($this->apiConfiguration->getOptions() as $option) {
            $this->config[$option->getOptionName()] = $option->getOptionValue();
        }
    }
    
    abstract public function analyze(string $targetType, string $targetValue): array;
    
    abstract public function getSupportedTypes(): array;
    
    protected function getApiKey(): ?string
    {
        return $this->config['api_key'] ?? null;
    }
    
    protected function getBaseUrl(): string
    {
        return $this->config['base_url'] ?? '';
    }
    
    protected function makeRequest(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->getBaseUrl() . $endpoint;
        
        try {
            $response = $this->httpClient->request($method, $url, $options);
            
            return [
                'status' => 'success',
                'data' => $response->toArray(),
                'status_code' => $response->getStatusCode()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    protected function formatResult(string $source, array $data, string $status = 'success', ?string $error = null): array
    {
        return [
            'source' => $source,
            'status' => $status,
            'data' => $data,
            'error' => $error,
            'timestamp' => new \DateTimeImmutable()
        ];
    }
}