<?php

namespace App\Repository;

use App\Entity\Target;
use App\Entity\Investigation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Target>
 */
class TargetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Target::class);
    }

    public function findByInvestigation(Investigation $investigation): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.investigation = :investigation')
            ->setParameter('investigation', $investigation)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingAnalysis(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();
    }

    public function countByType(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t.type, COUNT(t.id) as count')
            ->groupBy('t.type')
            ->getQuery()
            ->getResult();
    }
}