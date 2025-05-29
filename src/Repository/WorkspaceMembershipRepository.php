<?php
namespace App\Repository;

use App\Entity\WorkspaceMembership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WorkspaceMembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkspaceMembership::class);
    }
}
