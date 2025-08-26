<?php

namespace App\Repository;

use App\Entity\Investigation;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Investigation>
 */
class InvestigationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Investigation::class);
    }

    public function findByUser(User $user): array
    {
        $sql = 'SELECT * FROM investigation WHERE created_by_id = UNHEX(REPLACE(?, "-", "")) ORDER BY created_at DESC';
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->executeQuery($sql, [$user->getId()]);
        $results = $stmt->fetchAllAssociative();
        
        // Convert results to entities
        $investigations = [];
        foreach ($results as $result) {
            $investigation = $this->find($result['id']);
            if ($investigation) {
                $investigations[] = $investigation;
            }
        }
        return $investigations;
    }

    public function findByWorkspace(Workspace $workspace): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveInvestigations(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('statuses', ['draft', 'active'])
            ->orderBy('i.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUserAndWorkspace(User $user, Workspace $workspace): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.createdBy', 'u')
            ->andWhere('u.id = :userId')
            ->andWhere('i.workspace = :workspace')
            ->setParameter('userId', $user->getId())
            ->setParameter('workspace', $workspace)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentByUser(User $user, int $limit = 5): array
    {
        $sql = 'SELECT * FROM investigation WHERE created_by_id = UNHEX(REPLACE(?, "-", "")) ORDER BY created_at DESC LIMIT ' . $limit;
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->executeQuery($sql, [$user->getId()]);
        $results = $stmt->fetchAllAssociative();
        
        // Convert results to entities
        $investigations = [];
        foreach ($results as $result) {
            $investigation = $this->find($result['id']);
            if ($investigation) {
                $investigations[] = $investigation;
            }
        }
        return $investigations;
    }

    public function findByUserWithFilters(User $user, ?string $name = null, ?string $priority = null, int $page = 1, int $limit = 20): array
    {
        $sql = 'SELECT * FROM investigation WHERE created_by_id = UNHEX(REPLACE(?, "-", ""))';
        $params = [$user->getId()];
        
        if ($name) {
            $sql .= ' AND name LIKE ?';
            $params[] = '%' . $name . '%';
        }
        
        if ($priority) {
            $sql .= ' AND priority = ?';
            $params[] = $priority;
        }
        
        $sql .= ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit);
        
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->executeQuery($sql, $params);
        $results = $stmt->fetchAllAssociative();
        
        // Convert results to entities
        $investigations = [];
        foreach ($results as $result) {
            $investigation = $this->find($result['id']);
            if ($investigation) {
                $investigations[] = $investigation;
            }
        }
        return $investigations;
    }

    public function countByUserWithFilters(User $user, ?string $name = null, ?string $priority = null): int
    {
        $sql = 'SELECT COUNT(*) FROM investigation WHERE created_by_id = UNHEX(REPLACE(?, "-", ""))';
        $params = [$user->getId()];
        
        if ($name) {
            $sql .= ' AND name LIKE ?';
            $params[] = '%' . $name . '%';
        }
        
        if ($priority) {
            $sql .= ' AND priority = ?';
            $params[] = $priority;
        }
        
        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->executeQuery($sql, $params);
        return (int) $stmt->fetchOne();
    }
}