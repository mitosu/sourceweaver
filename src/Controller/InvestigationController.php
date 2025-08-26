<?php

namespace App\Controller;

use App\Entity\Investigation;
use App\Form\InvestigationType;
use App\Repository\InvestigationRepository;
use App\Repository\WorkspaceMembershipRepository;
use App\Service\ActivityLogService;
use App\Service\Workspace\GetUserWorkspaces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/investigations')]
#[IsGranted('ROLE_USER')]
final class InvestigationController extends AbstractController
{
    #[Route('/', name: 'investigation_index')]
    public function index(
        InvestigationRepository $repository,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $user = $this->getUser();
        $investigations = $repository->findByUser($user);
        $workspaces = $getUserWorkspaces($user);

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('dashboard'), 'icon' => 'bi bi-house-door'],
            ['label' => 'Investigaciones', 'icon' => 'bi bi-search']
        ];

        return $this->render('investigation/index.html.twig', [
            'investigations' => $investigations,
            'workspaces' => $workspaces,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    #[Route('/workspace/{workspaceId}', name: 'investigation_workspace')]
    public function workspace(
        string $workspaceId,
        InvestigationRepository $repository,
        WorkspaceMembershipRepository $membershipRepository,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $user = $this->getUser();
        $workspaces = $getUserWorkspaces($user);
        
        // Find the workspace from user workspaces
        $workspace = null;
        foreach ($workspaces as $ws) {
            if ($ws['id'] == $workspaceId) {
                // Get the actual workspace entity
                $membership = $membershipRepository->findOneBy([
                    'user' => $user,
                    'workspace' => $workspaceId
                ]);
                if ($membership) {
                    $workspace = $membership->getWorkspace();
                }
                break;
            }
        }

        if (!$workspace) {
            throw $this->createNotFoundException('Workspace not found');
        }

        $investigations = $repository->findByWorkspace($workspace);

        return $this->render('investigation/workspace.html.twig', [
            'investigations' => $investigations,
            'workspace' => $workspace,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/new', name: 'investigation_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $investigation = new Investigation();
        $investigation->setCreatedBy($this->getUser());

        $form = $this->createForm(InvestigationType::class, $investigation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($investigation);
            $entityManager->flush();

            $activityLogService->logInvestigationCreated($investigation);

            $this->addFlash('success', 'Investigación creada exitosamente');
            return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
        }

        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('investigation/new.html.twig', [
            'form' => $form,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/{id}', name: 'investigation_show')]
    public function show(
        Investigation $investigation,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $workspaces = $getUserWorkspaces($this->getUser());

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('dashboard'), 'icon' => 'bi bi-house-door'],
            ['label' => 'Investigaciones', 'url' => $this->generateUrl('investigation_index'), 'icon' => 'bi bi-search'],
            ['label' => $investigation->getName(), 'icon' => 'bi bi-file-text']
        ];

        return $this->render('investigation/show.html.twig', [
            'investigation' => $investigation,
            'workspaces' => $workspaces,
            'breadcrumbs' => $breadcrumbs
        ]);
    }

    #[Route('/{id}/edit', name: 'investigation_edit')]
    public function edit(
        Investigation $investigation,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $form = $this->createForm(InvestigationType::class, $investigation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $activityLogService->logInvestigationUpdated($investigation);

            $this->addFlash('success', 'Investigación actualizada exitosamente');
            return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
        }

        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('investigation/edit.html.twig', [
            'form' => $form,
            'investigation' => $investigation,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/{id}/delete', name: 'investigation_delete', methods: ['POST'])]
    public function delete(
        Investigation $investigation,
        EntityManagerInterface $entityManager
    ): Response {
        $entityManager->remove($investigation);
        $entityManager->flush();

        $this->addFlash('success', 'Investigación eliminada exitosamente');
        return $this->redirectToRoute('investigation_index');
    }
}