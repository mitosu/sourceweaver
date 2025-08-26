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
        return $this->createQueryBuilder('i')
            ->andWhere('i.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
            ->andWhere('i.createdBy = :user')
            ->andWhere('i.workspace = :workspace')
            ->setParameter('user', $user)
            ->setParameter('workspace', $workspace)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}