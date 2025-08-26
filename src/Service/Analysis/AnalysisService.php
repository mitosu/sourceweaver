<?php

namespace App\Service\Analysis;

use App\Entity\AnalysisResult;
use App\Entity\Target;
use App\Service\ActivityLogService;
use App\Service\Analysis\ApiAnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AnalysisService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ActivityLogService $activityLogService,
        private ?ApiAnalysisService $apiAnalysisService = null
    ) {}

    public function analyzeTarget(Target $target): array
    {
        // Always use API analysis service for real OSINT analysis
        if ($this->apiAnalysisService) {
            $this->logger->info('Starting API analysis for target', [
                'target_id' => $target->getId(),
                'target_type' => $target->getType(),
                'target_value' => $target->getValue()
            ]);
            
            return $this->apiAnalysisService->analyzeTarget($target);
        }
        
        // Fallback only if API service is not available
        $this->logger->warning('API analysis service not available, using simulated analysis', [
            'target_id' => $target->getId()
        ]);
        
        return $this->performSimulatedAnalysis($target);
    }
    
    private function performSimulatedAnalysis(Target $target): array
    {
        $results = [];
        
        try {
            // Update target status
            $target->setStatus('analyzing');
            $this->entityManager->flush();

            // Log analysis started
            $this->activityLogService->logAnalysisStarted($target->getInvestigation(), $target);

            // Simulate analysis process - will be replaced with real API calls
            $analysisData = $this->performAnalysis($target);
            
            // Store results
            foreach ($analysisData as $source => $data) {
                $result = new AnalysisResult();
                $result->setTarget($target);
                $result->setSource($source);
                $result->setData($data);
                $result->setStatus($data['status'] ?? 'success');
                
                if (isset($data['error'])) {
                    $result->setErrorMessage($data['error']);
                    $result->setStatus('error');
                }
                
                $this->entityManager->persist($result);
                $results[] = $result;
            }
            
            // Update target status and last analyzed time
            $target->setStatus('analyzed');
            $target->setLastAnalyzed(new \DateTimeImmutable());
            
            $this->entityManager->flush();
            
            // Log analysis completed
            $this->activityLogService->logAnalysisCompleted($target->getInvestigation(), $target, count($results));
            
            $this->logger->info('Target analyzed successfully', [
                'target_id' => $target->getId(),
                'target_type' => $target->getType(),
                'target_value' => $target->getValue(),
                'results_count' => count($results)
            ]);

        } catch (\Exception $e) {
            $target->setStatus('error');
            $this->entityManager->flush();
            
            // Log analysis failed
            $this->activityLogService->logAnalysisFailed($target->getInvestigation(), $target, $e->getMessage());
            
            $this->logger->error('Target analysis failed', [
                'target_id' => $target->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }

        return $results;
    }

    private function performAnalysis(Target $target): array
    {
        // Simulate different analysis based on target type
        $analysisData = [];
        
        switch ($target->getType()) {
            case 'ip':
                $analysisData['geolocation'] = $this->simulateGeolocationAnalysis($target->getValue());
                $analysisData['reputation'] = $this->simulateReputationAnalysis($target->getValue());
                break;
                
            case 'domain':
                $analysisData['dns'] = $this->simulateDnsAnalysis($target->getValue());
                $analysisData['whois'] = $this->simulateWhoisAnalysis($target->getValue());
                break;
                
            case 'url':
                $analysisData['url_analysis'] = $this->simulateUrlAnalysis($target->getValue());
                break;
                
            case 'hash':
                $analysisData['malware_scan'] = $this->simulateMalwareScan($target->getValue());
                break;
                
            case 'email':
                $analysisData['email_validation'] = $this->simulateEmailValidation($target->getValue());
                break;
                
            default:
                $analysisData['basic'] = [
                    'status' => 'success',
                    'message' => 'Target type analysis not implemented yet',
                    'timestamp' => (new \DateTimeImmutable())->format('c')
                ];
        }
        
        return $analysisData;
    }

    private function simulateGeolocationAnalysis(string $ip): array
    {
        // Simulate geolocation data
        $countries = ['ES', 'US', 'DE', 'FR', 'GB', 'RU', 'CN'];
        $cities = [
            'ES' => ['Madrid', 'Barcelona', 'Valencia'],
            'US' => ['New York', 'Los Angeles', 'Chicago'],
            'DE' => ['Berlin', 'Hamburg', 'Munich'],
            'FR' => ['Paris', 'Lyon', 'Marseille'],
            'GB' => ['London', 'Manchester', 'Birmingham'],
            'RU' => ['Moscow', 'St. Petersburg', 'Novosibirsk'],
            'CN' => ['Beijing', 'Shanghai', 'Shenzhen']
        ];
        
        $country = $countries[array_rand($countries)];
        $city = $cities[$country][array_rand($cities[$country])];
        
        return [
            'status' => 'success',
            'country' => $country,
            'city' => $city,
            'latitude' => round(rand(-90, 90) + rand(0, 100) / 100, 6),
            'longitude' => round(rand(-180, 180) + rand(0, 100) / 100, 6),
            'isp' => 'Example ISP ' . rand(1, 10),
            'org' => 'Example Organization',
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function simulateReputationAnalysis(string $ip): array
    {
        $reputation_score = rand(0, 100);
        $is_malicious = $reputation_score < 30;
        
        return [
            'status' => 'success',
            'reputation_score' => $reputation_score,
            'is_malicious' => $is_malicious,
            'categories' => $is_malicious ? ['spam', 'malware'] : [],
            'last_seen' => $is_malicious ? (new \DateTimeImmutable('-' . rand(1, 30) . ' days'))->format('c') : null,
            'reports_count' => $is_malicious ? rand(1, 100) : 0,
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function simulateDnsAnalysis(string $domain): array
    {
        return [
            'status' => 'success',
            'a_records' => [
                '192.0.2.' . rand(1, 254),
                '192.0.2.' . rand(1, 254)
            ],
            'mx_records' => [
                'mail.' . $domain,
                'mail2.' . $domain
            ],
            'ns_records' => [
                'ns1.example.com',
                'ns2.example.com'
            ],
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function simulateWhoisAnalysis(string $domain): array
    {
        return [
            'status' => 'success',
            'registrar' => 'Example Registrar Inc.',
            'creation_date' => (new \DateTimeImmutable('-' . rand(30, 3650) . ' days'))->format('Y-m-d'),
            'expiration_date' => (new \DateTimeImmutable('+' . rand(30, 365) . ' days'))->format('Y-m-d'),
            'registrant_country' => ['ES', 'US', 'DE', 'FR'][array_rand(['ES', 'US', 'DE', 'FR'])],
            'status' => ['active', 'inactive', 'pending'][array_rand(['active', 'inactive', 'pending'])],
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function simulateUrlAnalysis(string $url): array
    {
        $is_safe = rand(0, 1);
        
        return [
            'status' => 'success',
            'is_safe' => $is_safe,
            'categories' => $is_safe ? ['legitimate'] : ['phishing', 'malware'],
            'response_code' => rand(0, 1) ? 200 : 404,
            'title' => 'Example Page Title',
            'final_url' => $url,
            'redirects' => rand(0, 3),
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function simulateMalwareScan(string $hash): array
    {
        $is_malicious = rand(0, 100) < 25; // 25% chance of being malicious
        
        return [
            'status' => 'success',
            'is_malicious' => $is_malicious,
            'detections' => $is_malicious ? rand(1, 45) : 0,
            'total_engines' => 70,
            'scan_date' => (new \DateTimeImmutable())->format('c'),
            'malware_families' => $is_malicious ? ['Trojan.Generic', 'Win32.Malware'] : [],
            'threat_level' => $is_malicious ? ['high', 'medium', 'low'][array_rand(['high', 'medium', 'low'])] : 'clean',
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    private function simulateEmailValidation(string $email): array
    {
        $is_valid = rand(0, 100) < 80; // 80% chance of being valid
        
        return [
            'status' => 'success',
            'is_valid' => $is_valid,
            'is_disposable' => rand(0, 100) < 10,
            'mx_valid' => $is_valid && rand(0, 100) < 90,
            'domain_age_days' => rand(30, 3650),
            'reputation' => ['good', 'neutral', 'bad'][array_rand(['good', 'neutral', 'bad'])],
            'timestamp' => (new \DateTimeImmutable())->format('c')
        ];
    }

    public function analyzeBulkTargets(array $targets): array
    {
        $results = [];
        
        foreach ($targets as $target) {
            try {
                $results[$target->getId()->toString()] = $this->analyzeTarget($target);
            } catch (\Exception $e) {
                $results[$target->getId()->toString()] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
}