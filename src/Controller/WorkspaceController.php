<?php

namespace App\Controller;

use App\Entity\Workspace;
use App\Entity\ValueObject\WorkspaceName;
use App\Factory\WorkspaceFactory;
use App\Factory\WorkspaceMembershipFactory;
use App\Form\WorkspaceType;
use App\Repository\WorkspaceRepository;
use App\Repository\InvestigationRepository;
use App\Service\Workspace\GetUserWorkspaces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
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

        // Create form for new workspace
        $form = $this->createForm(WorkspaceType::class);

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('dashboard'), 'icon' => 'bi bi-house-door'],
            ['label' => 'Workspaces', 'icon' => 'bi bi-person-workspace']
        ];

        return $this->render('workspace/index.html.twig', [
            'workspaces' => $workspaces,
            'form' => $form,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    #[Route('/new', name: 'workspace_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(WorkspaceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $data = $form->getData();
                $user = $this->getUser();
                
                // Create workspace
                $workspaceName = new WorkspaceName($data['name']);
                $workspace = WorkspaceFactory::create($workspaceName, $user);
                $entityManager->persist($workspace);
                
                // Create membership for the owner
                $membership = WorkspaceMembershipFactory::create($workspace, $user, 'owner');
                $entityManager->persist($membership);
                
                $entityManager->flush();
                
                $this->addFlash('success', 'Workspace "' . $workspace->getName() . '" creado exitosamente.');
                
                return $this->redirectToRoute('workspace_show', ['id' => $workspace->getId()]);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al crear el workspace: ' . $e->getMessage());
            }
        }

        // If form has errors, add them to flash messages
        if ($form->isSubmitted() && !$form->isValid()) {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute('workspace_index');
    }

    #[Route('/{id}', name: 'workspace_show')]
    public function show(Request $request, string $id, GetUserWorkspaces $getUserWorkspaces, InvestigationRepository $investigationRepository, EntityManagerInterface $entityManager): Response
    {
        // Find workspace manually using the repository
        $workspaceRepository = $entityManager->getRepository(Workspace::class);
        $workspace = $workspaceRepository->find($id);
        
        if (!$workspace) {
            throw $this->createNotFoundException('Workspace no encontrado.');
        }
        
        $user = $this->getUser();
        $userWorkspaces = $getUserWorkspaces($user);
        
        // Verify user has access to this workspace
        $hasAccess = false;
        foreach ($userWorkspaces as $userWs) {
            if ($userWs['id'] === $workspace->getId()) {
                $hasAccess = true;
                break;
            }
        }

        if (!$hasAccess) {
            throw $this->createAccessDeniedException('No tienes acceso a este workspace.');
        }

        // Get filters from request
        $nameFilter = $request->query->get('name');
        $priorityFilter = $request->query->get('priority');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        // Get investigations for this workspace with filters and pagination
        $investigations = $investigationRepository->findByWorkspaceWithFilters($workspace, $nameFilter, $priorityFilter, $page, $limit);
        $totalInvestigations = $investigationRepository->countByWorkspaceWithFilters($workspace, $nameFilter, $priorityFilter);
        
        // Calculate pagination information
        $totalPages = max(1, ceil($totalInvestigations / $limit));
        $pagination = [
            'current' => $page,
            'total' => $totalPages,
            'hasNext' => $page < $totalPages,
            'hasPrev' => $page > 1,
            'next' => $page + 1,
            'prev' => $page - 1
        ];

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('dashboard'), 'icon' => 'bi bi-house-door'],
            ['label' => 'Workspaces', 'url' => $this->generateUrl('workspace_index'), 'icon' => 'bi bi-person-workspace'],
            ['label' => $workspace->getName(), 'icon' => 'bi bi-folder2']
        ];

        return $this->render('workspace/show.html.twig', [
            'workspace' => $workspace,
            'workspaces' => $userWorkspaces,
            'investigations' => $investigations,
            'totalInvestigations' => $totalInvestigations,
            'pagination' => $pagination,
            'filters' => [
                'name' => $nameFilter,
                'priority' => $priorityFilter
            ],
            'breadcrumbs' => $breadcrumbs
        ]);
    }
}
