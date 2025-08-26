<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Investigation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenStorageInterface $tokenStorage
    ) {}

    public function log(
        Investigation $investigation,
        string $action,
        string $description,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $metadata = null
    ): ActivityLog {
        $user = $this->getCurrentUser();
        
        $activityLog = new ActivityLog();
        $activityLog->setInvestigation($investigation);
        
        // Only set user if one is available (not in console context)
        if ($user) {
            $activityLog->setUser($user);
        }
        $activityLog->setAction($action);
        $activityLog->setDescription($description);
        
        if ($entityType) {
            $activityLog->setEntityType($entityType);
        }
        
        if ($entityId) {
            $activityLog->setEntityId(\Symfony\Component\Uid\Uuid::fromString($entityId));
        }
        
        if ($metadata) {
            $activityLog->setMetadata($metadata);
        }

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();

        return $activityLog;
    }

    public function logInvestigationCreated(Investigation $investigation): void
    {
        $this->log(
            $investigation,
            'investigation_created',
            "Se creó la investigación: {$investigation->getName()}",
            'Investigation',
            $investigation->getId()->toString(),
            [
                'priority' => $investigation->getPriority(),
                'status' => $investigation->getStatus()
            ]
        );
    }

    public function logInvestigationUpdated(Investigation $investigation): void
    {
        $this->log(
            $investigation,
            'investigation_updated',
            "Se actualizó la investigación: {$investigation->getName()}",
            'Investigation',
            $investigation->getId()->toString()
        );
    }

    public function logTargetCreated(Investigation $investigation, $target): void
    {
        $this->log(
            $investigation,
            'target_created',
            "Se añadió target {$target->getType()}: {$target->getValue()}",
            'Target',
            $target->getId()->toString(),
            [
                'target_type' => $target->getType(),
                'target_value' => $target->getValue()
            ]
        );
    }

    public function logTargetUpdated(Investigation $investigation, $target): void
    {
        $this->log(
            $investigation,
            'target_updated',
            "Se actualizó target {$target->getType()}: {$target->getValue()}",
            'Target',
            $target->getId()->toString()
        );
    }

    public function logTargetDeleted(Investigation $investigation, string $targetType, string $targetValue): void
    {
        $this->log(
            $investigation,
            'target_deleted',
            "Se eliminó target {$targetType}: {$targetValue}",
            'Target',
            null,
            [
                'target_type' => $targetType,
                'target_value' => $targetValue
            ]
        );
    }

    public function logAnalysisStarted(Investigation $investigation, $target): void
    {
        $this->log(
            $investigation,
            'analysis_started',
            "Se inició análisis de {$target->getType()}: {$target->getValue()}",
            'Target',
            $target->getId()->toString()
        );
    }

    public function logAnalysisCompleted(Investigation $investigation, $target, int $resultsCount): void
    {
        $this->log(
            $investigation,
            'analysis_completed',
            "Se completó análisis de {$target->getType()}: {$target->getValue()} ({$resultsCount} resultados)",
            'Target',
            $target->getId()->toString(),
            [
                'results_count' => $resultsCount
            ]
        );
    }

    public function logAnalysisFailed(Investigation $investigation, $target, string $error): void
    {
        $this->log(
            $investigation,
            'analysis_failed',
            "Falló análisis de {$target->getType()}: {$target->getValue()}",
            'Target',
            $target->getId()->toString(),
            [
                'error' => $error
            ]
        );
    }

    public function logBulkImport(Investigation $investigation, int $imported, int $skipped, int $errors): void
    {
        $this->log(
            $investigation,
            'bulk_import',
            "Importación masiva completada: {$imported} importados, {$skipped} omitidos, {$errors} errores",
            null,
            null,
            [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors
            ]
        );
    }

    private function getCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser() instanceof User) {
            // In console context or when no user is authenticated, return null
            return null;
        }
        
        return $token->getUser();
    }
}