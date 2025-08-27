<?php

namespace App\Service\Analysis;

use App\Entity\AnalysisResult;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OsintToolsService
{
    private const PYTHON_API_BASE_URL = 'http://python-osint:8001/api/v1';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private HttpClientInterface $httpClient
    ) {}

    public function analyzeTargetWithOsintTools(Target $target): array
    {
        $results = [];
        $osintTools = $target->getOsintTools();

        if (empty($osintTools)) {
            $this->logger->info('No OSINT tools selected for target', [
                'target_id' => $target->getId(),
                'target_type' => $target->getType(),
                'target_value' => $target->getValue()
            ]);
            return [];
        }

        $this->logger->info('Starting OSINT tools analysis', [
            'target_id' => $target->getId(),
            'target_type' => $target->getType(),
            'target_value' => $target->getValue(),
            'osint_tools' => $osintTools
        ]);

        foreach ($osintTools as $tool) {
            try {
                $result = $this->analyzeWithTool($target, $tool);
                if ($result) {
                    $results[] = $result;
                }
            } catch (\Exception $e) {
                $this->logger->error('OSINT tool analysis failed', [
                    'tool' => $tool,
                    'target_id' => $target->getId(),
                    'error' => $e->getMessage()
                ]);

                // Create error result
                $errorResult = new AnalysisResult();
                $errorResult->setTarget($target);
                $errorResult->setSource('OSINT-' . ucfirst($tool));
                $errorResult->setData([]);
                $errorResult->setStatus('error');
                $errorResult->setErrorMessage($e->getMessage());

                $this->entityManager->persist($errorResult);
                $results[] = $errorResult;
            }
        }

        return $results;
    }

    private function analyzeWithTool(Target $target, string $tool): ?AnalysisResult
    {
        return match ($tool) {
            'virustotal' => $this->analyzeWithVirusTotal($target),
            default => null
        };
    }

    private function analyzeWithVirusTotal(Target $target): ?AnalysisResult
    {
        $targetType = $target->getType();
        $targetValue = $target->getValue();

        try {
            // For URLs we need special handling
            if ($targetType === 'url') {
                return $this->handleUrlAnalysis($target, $targetValue);
            }

            // Map target types to VirusTotal endpoints for direct analysis
            $endpoint = match ($targetType) {
                'ip' => "/virustotal/ip/{$targetValue}",
                'domain' => "/virustotal/domains/{$targetValue}",
                'hash' => "/virustotal/files/{$targetValue}",
                default => null
            };

            if (!$endpoint) {
                $this->logger->warning('VirusTotal does not support target type', [
                    'target_type' => $targetType,
                    'target_value' => $targetValue
                ]);
                return null;
            }

            // Direct analysis for IP, domain, hash
            $url = self::PYTHON_API_BASE_URL . $endpoint;
            $this->logger->info('Calling VirusTotal API via Python service', [
                'url' => $url,
                'target_type' => $targetType,
                'target_value' => $targetValue
            ]);

            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            $data = $response->toArray();

            $this->logger->info('VirusTotal analysis completed', [
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'status' => $data['status'] ?? 'unknown'
            ]);

            $result = new AnalysisResult();
            $result->setTarget($target);
            $result->setSource('VirusTotal');
            $result->setData($data);
            $result->setStatus('success');

            $this->entityManager->persist($result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('VirusTotal API call failed', [
                'target_type' => $targetType,
                'target_value' => $targetValue,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function handleUrlAnalysis(Target $target, string $url): ?AnalysisResult
    {
        try {
            // First, submit URL for analysis
            $submitUrl = self::PYTHON_API_BASE_URL . '/virustotal/url-scan';
            $this->logger->info('Submitting URL to VirusTotal', [
                'url' => $url,
                'submit_endpoint' => $submitUrl
            ]);

            $response = $this->httpClient->request('GET', $submitUrl . '?url=' . urlencode($url), [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            $data = $response->toArray();

            $this->logger->info('VirusTotal URL analysis completed', [
                'url' => $url,
                'status' => $data['status'] ?? 'unknown'
            ]);

            $result = new AnalysisResult();
            $result->setTarget($target);
            $result->setSource('VirusTotal');
            $result->setData($data);
            $result->setStatus('success');

            $this->entityManager->persist($result);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('VirusTotal URL analysis failed', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAvailableOsintTools(): array
    {
        return [
            'virustotal' => [
                'name' => 'VirusTotal',
                'description' => 'Análisis de archivos, URLs, dominios e IPs para detectar malware y amenazas',
                'supported_types' => ['ip', 'domain', 'url', 'hash'],
                'icon' => 'bi-shield-check'
            ]
        ];
    }

    public function testOsintToolConnection(string $tool): bool
    {
        return match ($tool) {
            'virustotal' => $this->testVirusTotalConnection(),
            default => false
        };
    }

    private function testVirusTotalConnection(): bool
    {
        try {
            $response = $this->httpClient->request('GET', self::PYTHON_API_BASE_URL . '/virustotal/health', [
                'timeout' => 10
            ]);

            $data = $response->toArray();
            return $data['status'] === 'healthy';

        } catch (\Exception $e) {
            $this->logger->error('VirusTotal connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}