<?php

namespace App\Repository;

use App\Entity\ApiConfiguration;
use App\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiConfiguration>
 */
class ApiConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiConfiguration::class);
    }

    public function findByWorkspace(Workspace $workspace): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByWorkspace(Workspace $workspace): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.workspace = :workspace')
            ->andWhere('a.isActive = true')
            ->setParameter('workspace', $workspace)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}