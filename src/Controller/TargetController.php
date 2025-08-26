<?php

namespace App\Controller;

use App\Entity\Investigation;
use App\Entity\Target;
use App\Form\BulkTargetImportType;
use App\Form\TargetType;
use App\Service\ActivityLogService;
use App\Service\Analysis\AnalysisService;
use App\Service\Target\BulkTargetImportService;
use App\Service\Workspace\GetUserWorkspaces;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/investigations/{investigationId}/targets')]
#[IsGranted('ROLE_USER')]
final class TargetController extends AbstractController
{
    #[Route('/new', name: 'target_new')]
    public function new(
        #[MapEntity(mapping: ['investigationId' => 'id'])] Investigation $investigation,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $target = new Target();
        $target->setInvestigation($investigation);

        $form = $this->createForm(TargetType::class, $target);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($target);
            $entityManager->flush();

            $activityLogService->logTargetCreated($investigation, $target);

            $this->addFlash('success', 'Target añadido exitosamente');
            return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
        }

        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('target/new.html.twig', [
            'form' => $form,
            'investigation' => $investigation,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/{id}/edit', name: 'target_edit')]
    public function edit(
        #[MapEntity(mapping: ['investigationId' => 'id'])] Investigation $investigation,
        Target $target,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $form = $this->createForm(TargetType::class, $target);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $activityLogService->logTargetUpdated($investigation, $target);

            $this->addFlash('success', 'Target actualizado exitosamente');
            return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
        }

        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('target/edit.html.twig', [
            'form' => $form,
            'target' => $target,
            'investigation' => $investigation,
            'workspaces' => $workspaces
        ]);
    }

    #[Route('/{id}/delete', name: 'target_delete', methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['investigationId' => 'id'])] Investigation $investigation,
        Target $target,
        EntityManagerInterface $entityManager,
        ActivityLogService $activityLogService
    ): Response {
        $targetType = $target->getType();
        $targetValue = $target->getValue();
        
        $entityManager->remove($target);
        $entityManager->flush();

        $activityLogService->logTargetDeleted($investigation, $targetType, $targetValue);

        $this->addFlash('success', 'Target eliminado exitosamente');
        return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
    }

    #[Route('/{id}/analyze', name: 'target_analyze', methods: ['POST'])]
    public function analyze(
        #[MapEntity(mapping: ['investigationId' => 'id'])] Investigation $investigation,
        Target $target,
        AnalysisService $analysisService
    ): Response {
        try {
            $analysisService->analyzeTarget($target);
            $this->addFlash('success', 'Análisis completado exitosamente');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error durante el análisis: ' . $e->getMessage());
        }

        return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
    }

    #[Route('/{id}/results', name: 'target_results')]
    public function results(
        #[MapEntity(mapping: ['investigationId' => 'id'])] Investigation $investigation,
        Target $target,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $workspaces = $getUserWorkspaces($this->getUser());

        return $this->render('target/results.html.twig', [
            'target' => $target,
            'investigation' => $investigation,
            'workspaces' => $workspaces,
            'results' => $target->getAnalysisResults()
        ]);
    }

    #[Route('/bulk-import', name: 'target_bulk_import')]
    public function bulkImport(
        #[MapEntity(mapping: ['investigationId' => 'id'])] Investigation $investigation,
        Request $request,
        BulkTargetImportService $importService,
        GetUserWorkspaces $getUserWorkspaces
    ): Response {
        $form = $this->createForm(BulkTargetImportType::class);
        $form->handleRequest($request);

        $importResults = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            try {
                $importResults = $importService->importTargets(
                    $investigation, 
                    $data['targets'], 
                    $data['autoAnalyze'] ?? false
                );

                $this->addFlash('success', sprintf(
                    'Importación completada: %d targets importados, %d omitidos, %d errores',
                    count($importResults['imported']),
                    count($importResults['skipped']),
                    count($importResults['errors'])
                ));

                if (count($importResults['imported']) > 0 && count($importResults['errors']) === 0 && count($importResults['skipped']) === 0) {
                    return $this->redirectToRoute('investigation_show', ['id' => $investigation->getId()]);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error durante la importación: ' . $e->getMessage());
            }
        }

        $workspaces = $getUserWorkspaces($this->getUser());
        $supportedFormats = $importService->getSupportedFormats();

        return $this->render('target/bulk_import.html.twig', [
            'form' => $form,
            'investigation' => $investigation,
            'workspaces' => $workspaces,
            'importResults' => $importResults,
            'supportedFormats' => $supportedFormats
        ]);
    }
}