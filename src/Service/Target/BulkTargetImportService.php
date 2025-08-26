<?php

namespace App\Service\Target;

use App\Entity\Investigation;
use App\Entity\Target;
use App\Service\ActivityLogService;
use App\Service\Analysis\AnalysisService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BulkTargetImportService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnalysisService $analysisService,
        private LoggerInterface $logger,
        private ActivityLogService $activityLogService
    ) {}

    public function importTargets(Investigation $investigation, string $targetsText, bool $autoAnalyze = false): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $targetsText)));
        $results = [
            'imported' => [],
            'skipped' => [],
            'errors' => []
        ];

        foreach ($lines as $lineNumber => $line) {
            try {
                if (empty($line)) {
                    continue;
                }

                $targetType = $this->detectTargetType($line);
                
                if (!$targetType) {
                    $results['skipped'][] = [
                        'line' => $lineNumber + 1,
                        'value' => $line,
                        'reason' => 'Tipo de target no detectado'
                    ];
                    continue;
                }

                // Check if target already exists
                $existingTarget = $this->entityManager->getRepository(Target::class)
                    ->findOneBy([
                        'investigation' => $investigation,
                        'type' => $targetType,
                        'value' => $line
                    ]);

                if ($existingTarget) {
                    $results['skipped'][] = [
                        'line' => $lineNumber + 1,
                        'value' => $line,
                        'reason' => 'Target ya existe en la investigación'
                    ];
                    continue;
                }

                $target = new Target();
                $target->setInvestigation($investigation);
                $target->setType($targetType);
                $target->setValue($line);
                $target->setDescription('Importado en lote');

                $this->entityManager->persist($target);
                
                $results['imported'][] = [
                    'line' => $lineNumber + 1,
                    'value' => $line,
                    'type' => $targetType,
                    'target' => $target
                ];

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'line' => $lineNumber + 1,
                    'value' => $line,
                    'error' => $e->getMessage()
                ];
                
                $this->logger->error('Error importing target', [
                    'line' => $lineNumber + 1,
                    'value' => $line,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Save all targets
        if (!empty($results['imported'])) {
            $this->entityManager->flush();
            
            // Auto-analyze if requested
            if ($autoAnalyze) {
                foreach ($results['imported'] as &$import) {
                    try {
                        $this->analysisService->analyzeTarget($import['target']);
                        $import['analyzed'] = true;
                    } catch (\Exception $e) {
                        $import['analyzed'] = false;
                        $import['analysis_error'] = $e->getMessage();
                    }
                }
            }
        }

        // Log bulk import activity
        $this->activityLogService->logBulkImport(
            $investigation,
            count($results['imported']),
            count($results['skipped']),
            count($results['errors'])
        );

        $this->logger->info('Bulk import completed', [
            'investigation_id' => $investigation->getId(),
            'imported' => count($results['imported']),
            'skipped' => count($results['skipped']),
            'errors' => count($results['errors']),
            'auto_analyze' => $autoAnalyze
        ]);

        return $results;
    }

    private function detectTargetType(string $value): ?string
    {
        $value = trim($value);
        
        // IP Address (IPv4)
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'ip';
        }
        
        // Email
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        
        // URL
        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        }
        
        // Hash (MD5, SHA1, SHA256)
        if (preg_match('/^[a-f0-9]{32}$/i', $value)) { // MD5
            return 'hash';
        }
        if (preg_match('/^[a-f0-9]{40}$/i', $value)) { // SHA1
            return 'hash';
        }
        if (preg_match('/^[a-f0-9]{64}$/i', $value)) { // SHA256
            return 'hash';
        }
        
        // Domain (basic validation)
        if (preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/', $value) 
            && strpos($value, '.') !== false) {
            return 'domain';
        }
        
        // Phone (basic validation for international format)
        if (preg_match('/^\+?[1-9]\d{1,14}$/', $value)) {
            return 'phone';
        }
        
        return null;
    }

    public function getSupportedFormats(): array
    {
        return [
            'ip' => [
                'name' => 'Dirección IP',
                'examples' => ['192.168.1.1', '8.8.8.8', '10.0.0.1'],
                'description' => 'Direcciones IPv4'
            ],
            'domain' => [
                'name' => 'Dominio',
                'examples' => ['example.com', 'google.com', 'malicious.domain'],
                'description' => 'Nombres de dominio'
            ],
            'url' => [
                'name' => 'URL',
                'examples' => ['https://example.com', 'http://malicious.site/path'],
                'description' => 'URLs completas con protocolo'
            ],
            'email' => [
                'name' => 'Email',
                'examples' => ['user@domain.com', 'admin@example.org'],
                'description' => 'Direcciones de correo electrónico'
            ],
            'hash' => [
                'name' => 'Hash',
                'examples' => ['d41d8cd98f00b204e9800998ecf8427e', '356a192b7913b04c54574d18c28d46e6395428ab'],
                'description' => 'Hashes MD5, SHA1 o SHA256'
            ],
            'phone' => [
                'name' => 'Teléfono',
                'examples' => ['+34600123456', '+1234567890'],
                'description' => 'Números de teléfono en formato internacional'
            ]
        ];
    }
}