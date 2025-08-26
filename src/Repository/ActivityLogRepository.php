<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\Investigation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    public function findByInvestigation(Investigation $investigation, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.investigation = :investigation')
            ->setParameter('investigation', $investigation)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentActivity(int $days = 7, int $limit = 100): array
    {
        $since = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('al')
            ->andWhere('al.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}