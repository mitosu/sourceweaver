<?php

namespace App\Controller;

use App\Entity\Investigation;
use App\Repository\InvestigationRepository;
use App\Repository\TargetRepository;
use App\Service\Workspace\GetUserWorkspaces;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/investigations/{id}/dashboard')]
#[IsGranted('ROLE_USER')]
final class InvestigationDashboardController extends AbstractController
{
    #[Route('/', name: 'investigation_dashboard')]
    public function dashboard(
        Investigation $investigation,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('investigation/dashboard.html.twig', [
            'investigation' => $investigation,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/api/stats', name: 'investigation_dashboard_stats')]
    public function getStats(Investigation $investigation): JsonResponse
    {
        $targets = $investigation->getTargets();
        
        // Basic statistics
        $stats = [
            'total_targets' => $targets->count(),
            'analyzed_targets' => 0,
            'pending_targets' => 0,
            'error_targets' => 0,
            'total_results' => 0
        ];

        // Target type distribution
        $typeDistribution = [];
        $statusDistribution = [];
        $analysisResults = [];
        $threatLevels = ['clean' => 0, 'low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        $recentActivity = [];

        foreach ($targets as $target) {
            // Type distribution
            $type = $target->getType();
            $typeDistribution[$type] = ($typeDistribution[$type] ?? 0) + 1;
            
            // Status distribution
            $status = $target->getStatus();
            $statusDistribution[$status] = ($statusDistribution[$status] ?? 0) + 1;
            
            if ($status === 'analyzed') {
                $stats['analyzed_targets']++;
            } elseif ($status === 'pending') {
                $stats['pending_targets']++;
            } elseif ($status === 'error') {
                $stats['error_targets']++;
            }

            // Analysis results
            $results = $target->getAnalysisResults();
            $stats['total_results'] += $results->count();
            
            foreach ($results as $result) {
                $source = $result->getSource();
                $analysisResults[$source] = ($analysisResults[$source] ?? 0) + 1;
                
                // Simulate threat level analysis
                $data = $result->getData();
                if ($result->getStatus() === 'success') {
                    $threatLevel = $this->calculateThreatLevel($result->getSource(), $data);
                    $threatLevels[$threatLevel]++;
                }
                
                // Recent activity (last 7 days)
                $analyzedAt = $result->getAnalyzedAt();
                if ($analyzedAt && $analyzedAt > new \DateTimeImmutable('-7 days')) {
                    $dateKey = $analyzedAt->format('Y-m-d');
                    $recentActivity[$dateKey] = ($recentActivity[$dateKey] ?? 0) + 1;
                }
            }
        }

        // Fill missing days in recent activity
        for ($i = 6; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable("-{$i} days"))->format('Y-m-d');
            if (!isset($recentActivity[$date])) {
                $recentActivity[$date] = 0;
            }
        }
        ksort($recentActivity);

        return new JsonResponse([
            'stats' => $stats,
            'typeDistribution' => $typeDistribution,
            'statusDistribution' => $statusDistribution,
            'analysisResults' => $analysisResults,
            'threatLevels' => $threatLevels,
            'recentActivity' => $recentActivity
        ]);
    }

    #[Route('/api/timeline', name: 'investigation_dashboard_timeline')]
    public function getTimeline(Investigation $investigation): JsonResponse
    {
        $targets = $investigation->getTargets();
        $timeline = [];
        
        // Investigation created
        $timeline[] = [
            'date' => $investigation->getCreatedAt()->format('c'),
            'type' => 'investigation_created',
            'title' => 'Investigación creada',
            'description' => 'Se inició la investigación: ' . $investigation->getName(),
            'icon' => 'bi-plus-circle',
            'color' => 'success'
        ];

        foreach ($targets as $target) {
            // Target created
            $timeline[] = [
                'date' => $target->getCreatedAt()->format('c'),
                'type' => 'target_added',
                'title' => 'Target añadido',
                'description' => "Target {$target->getType()}: {$target->getValue()}",
                'icon' => 'bi-bullseye',
                'color' => 'info'
            ];

            // Analysis results
            foreach ($target->getAnalysisResults() as $result) {
                $color = $result->getStatus() === 'success' ? 'primary' : 'danger';
                $icon = $result->getStatus() === 'success' ? 'bi-check-circle' : 'bi-x-circle';
                
                $timeline[] = [
                    'date' => $result->getAnalyzedAt()->format('c'),
                    'type' => 'analysis_completed',
                    'title' => 'Análisis completado',
                    'description' => "Análisis {$result->getSource()} para {$target->getValue()}",
                    'icon' => $icon,
                    'color' => $color,
                    'target_id' => $target->getId()->toString(),
                    'result_status' => $result->getStatus()
                ];
            }
        }

        // Sort by date descending
        usort($timeline, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return new JsonResponse(['timeline' => array_slice($timeline, 0, 20)]); // Last 20 events
    }

    #[Route('/api/threats', name: 'investigation_dashboard_threats')]
    public function getThreats(Investigation $investigation): JsonResponse
    {
        $targets = $investigation->getTargets();
        $threats = [];
        
        foreach ($targets as $target) {
            foreach ($target->getAnalysisResults() as $result) {
                if ($result->getStatus() === 'success') {
                    $threat = $this->analyzeThreatFromResult($target, $result);
                    if ($threat['level'] !== 'clean') {
                        $threats[] = $threat;
                    }
                }
            }
        }
        
        // Sort by threat level (critical -> high -> medium -> low)
        $levelOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        usort($threats, function($a, $b) use ($levelOrder) {
            return ($levelOrder[$b['level']] ?? 0) - ($levelOrder[$a['level']] ?? 0);
        });
        
        return new JsonResponse(['threats' => array_slice($threats, 0, 10)]); // Top 10 threats
    }

    private function calculateThreatLevel(string $source, array $data): string
    {
        switch ($source) {
            case 'reputation':
                $score = $data['reputation_score'] ?? 50;
                if ($data['is_malicious'] ?? false) return 'high';
                if ($score < 30) return 'medium';
                if ($score < 50) return 'low';
                return 'clean';
                
            case 'malware_scan':
                if ($data['is_malicious'] ?? false) {
                    $threatLevel = $data['threat_level'] ?? 'medium';
                    return match($threatLevel) {
                        'high' => 'critical',
                        'medium' => 'high',
                        'low' => 'medium',
                        default => 'medium'
                    };
                }
                return 'clean';
                
            case 'url_analysis':
                if (!($data['is_safe'] ?? true)) {
                    $categories = $data['categories'] ?? [];
                    if (in_array('malware', $categories)) return 'high';
                    if (in_array('phishing', $categories)) return 'high';
                    return 'medium';
                }
                return 'clean';
                
            default:
                return 'clean';
        }
    }

    private function analyzeThreatFromResult($target, $result): array
    {
        $data = $result->getData();
        $level = $this->calculateThreatLevel($result->getSource(), $data);
        
        $threat = [
            'target_id' => $target->getId()->toString(),
            'target_type' => $target->getType(),
            'target_value' => $target->getValue(),
            'source' => $result->getSource(),
            'level' => $level,
            'analyzed_at' => $result->getAnalyzedAt()->format('c'),
            'description' => $this->getThreatDescription($result->getSource(), $data, $level)
        ];
        
        return $threat;
    }

    private function getThreatDescription(string $source, array $data, string $level): string
    {
        switch ($source) {
            case 'reputation':
                if ($data['is_malicious'] ?? false) {
                    $reports = $data['reports_count'] ?? 0;
                    return "IP reportada como maliciosa ($reports reportes)";
                }
                $score = $data['reputation_score'] ?? 50;
                return "Reputación baja (score: $score/100)";
                
            case 'malware_scan':
                if ($data['is_malicious'] ?? false) {
                    $detections = $data['detections'] ?? 0;
                    $total = $data['total_engines'] ?? 70;
                    return "Malware detectado ($detections/$total motores)";
                }
                return "Hash limpio";
                
            case 'url_analysis':
                if (!($data['is_safe'] ?? true)) {
                    $categories = $data['categories'] ?? [];
                    return "URL peligrosa: " . implode(', ', $categories);
                }
                return "URL segura";
                
            default:
                return "Análisis completado";
        }
    }
}