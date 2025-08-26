<?php

namespace App\Service\Analysis;

use App\Entity\AnalysisResult;
use App\Entity\Target;
use App\Repository\ApiConfigurationRepository;
use App\Service\ActivityLogService;
use App\Service\Analysis\Provider\AbstractApiProvider;
use App\Service\Analysis\Provider\VirusTotalProvider;
use App\Service\Analysis\Provider\AbuseIPDBProvider;
use App\Service\Analysis\Provider\URLVoidProvider;
use App\Service\Analysis\Provider\FastApiProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiAnalysisService
{
    private array $providers = [];
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ActivityLogService $activityLogService,
        private ApiConfigurationRepository $apiConfigurationRepository,
        private HttpClientInterface $httpClient
    ) {
        $this->initializeProviders();
    }
    
    public function analyzeTarget(Target $target): array
    {
        $results = [];
        
        try {
            $target->setStatus('analyzing');
            $this->entityManager->flush();

            $this->activityLogService->logAnalysisStarted($target->getInvestigation(), $target);

            foreach ($this->providers as $provider) {
                if (in_array($target->getType(), $provider->getSupportedTypes())) {
                    try {
                        $providerResult = $provider->analyze($target->getType(), $target->getValue());
                        
                        $result = new AnalysisResult();
                        $result->setTarget($target);
                        $result->setSource($providerResult['source']);
                        $result->setData($providerResult['data']);
                        $result->setStatus($providerResult['status']);
                        
                        if (isset($providerResult['error'])) {
                            $result->setErrorMessage($providerResult['error']);
                        }
                        
                        $this->entityManager->persist($result);
                        $results[] = $result;
                        
                    } catch (\Exception $e) {
                        $this->logger->error('Provider analysis failed', [
                            'provider' => get_class($provider),
                            'target_id' => $target->getId(),
                            'error' => $e->getMessage()
                        ]);
                        
                        $result = new AnalysisResult();
                        $result->setTarget($target);
                        $result->setSource($this->getProviderName($provider));
                        $result->setData([]);
                        $result->setStatus('error');
                        $result->setErrorMessage($e->getMessage());
                        
                        $this->entityManager->persist($result);
                        $results[] = $result;
                    }
                }
            }
            
            $target->setStatus('analyzed');
            $target->setLastAnalyzed(new \DateTimeImmutable());
            
            $this->entityManager->flush();

            $this->activityLogService->logAnalysisCompleted($target->getInvestigation(), $target, count($results));
            
            $this->logger->info('Target analyzed successfully with API providers', [
                'target_id' => $target->getId(),
                'target_type' => $target->getType(),
                'target_value' => $target->getValue(),
                'results_count' => count($results),
                'providers_used' => count($this->providers)
            ]);

        } catch (\Exception $e) {
            $target->setStatus('error');
            $this->entityManager->flush();

            $this->activityLogService->logAnalysisFailed($target->getInvestigation(), $target, $e->getMessage());
            
            $this->logger->error('Target analysis failed', [
                'target_id' => $target->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }

        return $results;
    }
    
    private function initializeProviders(): void
    {
        $apiConfigs = $this->apiConfigurationRepository->findBy(['isActive' => true]);
        
        foreach ($apiConfigs as $config) {
            $provider = $this->createProvider($config->getName(), $config);
            if ($provider) {
                $this->providers[] = $provider;
            }
        }
    }
    
    private function createProvider(string $name, $config): ?AbstractApiProvider
    {
        return match (strtolower($name)) {
            'virustotal' => new VirusTotalProvider($this->httpClient, $config),
            'abuseipdb' => new AbuseIPDBProvider($this->httpClient, $config),
            'urlvoid' => new URLVoidProvider($this->httpClient, $config),
            'fastapi-python', 'fastapi', 'python-scripts', 'python-osint' => new FastApiProvider($this->httpClient, $config, $this->logger),
            default => null
        };
    }
    
    private function getProviderName(AbstractApiProvider $provider): string
    {
        $className = get_class($provider);
        $parts = explode('\\', $className);
        return str_replace('Provider', '', end($parts));
    }
    
    public function getAvailableProviders(): array
    {
        $providers = [];
        foreach ($this->providers as $provider) {
            $providers[] = [
                'name' => $this->getProviderName($provider),
                'supported_types' => $provider->getSupportedTypes()
            ];
        }
        return $providers;
    }
}