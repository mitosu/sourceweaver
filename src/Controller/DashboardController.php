<?php

namespace App\Controller;

use App\Repository\InvestigationRepository;
use App\Repository\ActivityLogRepository;
use App\Service\Workspace\GetUserWorkspaces;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(
        GetUserWorkspaces $getUserWorkspaces,
        InvestigationRepository $investigationRepository,
        ActivityLogRepository $activityLogRepository
    ): Response {
        $user = $this->getUser();
        $workspaces = $getUserWorkspaces($user);
        
        // Estadísticas del dashboard
        $investigations = $investigationRepository->findByUser($user);
        $recentActivity = $activityLogRepository->findRecentActivity(7, 10);
        
        // Calcular estadísticas
        $stats = [
            'total_investigations' => count($investigations),
            'active_investigations' => count(array_filter($investigations, fn($i) => $i->getStatus() === 'active')),
            'completed_investigations' => count(array_filter($investigations, fn($i) => $i->getStatus() === 'completed')),
            'total_targets' => array_sum(array_map(fn($i) => count($i->getTargets()), $investigations)),
        ];
        
        // Investigaciones recientes
        $recentInvestigations = array_slice($investigations, 0, 5);
        
        $breadcrumbs = [
            ['label' => 'Dashboard', 'icon' => 'bi bi-house-door']
        ];

        return $this->render('dashboard/index.html.twig', [
            'workspaces' => $workspaces,
            'stats' => $stats,
            'recentInvestigations' => $recentInvestigations,
            'recentActivity' => $recentActivity,
            'breadcrumbs' => $breadcrumbs
        ]);
    }
}
