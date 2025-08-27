<?php

namespace App\Controller;

use App\Entity\Workspace;
use App\Repository\WorkspaceRepository;
use App\Service\Workspace\GetUserWorkspaces;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/workspaces')]
#[IsGranted('ROLE_USER')]
final class WorkspaceController extends AbstractController
{
    #[Route('/', name: 'workspace_index')]
    public function index(GetUserWorkspaces $getUserWorkspaces): Response
    {
        $user = $this->getUser();
        $workspaces = $getUserWorkspaces($user);

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('dashboard'), 'icon' => 'bi bi-house-door'],
            ['label' => 'Workspaces', 'icon' => 'bi bi-person-workspace']
        ];

        return $this->render('workspace/index.html.twig', [
            'workspaces' => $workspaces,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    #[Route('/{id}', name: 'workspace_show')]
    public function show(Workspace $workspace, GetUserWorkspaces $getUserWorkspaces): Response
    {
        $user = $this->getUser();
        $userWorkspaces = $getUserWorkspaces($user);
        
        // Verify user has access to this workspace
        $hasAccess = false;
        foreach ($userWorkspaces as $userWs) {
            if ($userWs->getId() === $workspace->getId()) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            throw $this->createAccessDeniedException('No tienes acceso a este workspace.');
        }

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('dashboard'), 'icon' => 'bi bi-house-door'],
            ['label' => 'Workspaces', 'url' => $this->generateUrl('workspace_index'), 'icon' => 'bi bi-person-workspace'],
            ['label' => $workspace->getName(), 'icon' => 'bi bi-folder2']
        ];

        return $this->render('workspace/show.html.twig', [
            'workspace' => $workspace,
            'workspaces' => $userWorkspaces,
            'breadcrumbs' => $breadcrumbs
        ]);
    }
}
