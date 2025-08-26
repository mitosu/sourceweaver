<?php

namespace App\Repository;

use App\Entity\AnalysisResult;
use App\Entity\Target;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalysisResult>
 */
class AnalysisResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalysisResult::class);
    }

    public function findByTarget(Target $target): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.target = :target')
            ->setParameter('target', $target)
            ->orderBy('ar.analyzedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBySource(string $source): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.source = :source')
            ->setParameter('source', $source)
            ->orderBy('ar.analyzedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findSuccessfulResults(): array
    {
        return $this->createQueryBuilder('ar')
            ->andWhere('ar.status = :status')
            ->setParameter('status', 'success')
            ->orderBy('ar.analyzedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}