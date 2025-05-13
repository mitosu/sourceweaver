<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        $workspaces = [
            [
                'name' => 'Administración',
                'id' => 1,
                'routes' => [
                    ['label' => 'Overview', 'icon' => 'bi-grid', 'path' => 'workspace_overview', 'params' => ['id' => 1]],
                    ['label' => 'Documentos', 'icon' => 'bi-file-earmark-text', 'path' => 'workspace_documents', 'params' => ['id' => 1]],
                ]
            ],
            [
                'name' => 'Development',
                'id' => 2,
                'routes' => [
                    ['label' => 'Overview', 'icon' => 'bi-grid', 'path' => 'workspace_overview', 'params' => ['id' => 2]],
                    ['label' => 'Tickets', 'icon' => 'bi-life-preserver', 'path' => 'workspace_tickets', 'params' => ['id' => 2]],
                ]
            ],
        ];

        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'workspaces' => $workspaces
        ]);
    }
}
