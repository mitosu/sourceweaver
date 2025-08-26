<?php

namespace App\Controller\Api;

use App\Entity\Investigation;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/investigations')]
#[IsGranted('ROLE_USER')]
final class InvestigationApiController extends AbstractController
{
    #[Route('/{id}/activity', name: 'api_investigation_activity')]
    public function activityLog(
        Investigation $investigation,
        ActivityLogRepository $activityLogRepository
    ): JsonResponse {
        $activities = $activityLogRepository->findByInvestigation($investigation, 50);
        
        $data = [];
        foreach ($activities as $activity) {
            $data[] = [
                'id' => $activity->getId()->toString(),
                'action' => $activity->getAction(),
                'description' => $activity->getDescription(),
                'icon' => $activity->getActionIcon(),
                'color' => $activity->getActionColor(),
                'user' => $activity->getUser()->getEmail(),
                'createdAt' => $activity->getCreatedAt()->format('c'),
                'metadata' => $activity->getMetadata()
            ];
        }
        
        return new JsonResponse($data);
    }
}