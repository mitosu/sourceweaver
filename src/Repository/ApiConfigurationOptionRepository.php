<?php

namespace App\Repository;

use App\Entity\ApiConfigurationOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiConfigurationOption>
 */
class ApiConfigurationOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiConfigurationOption::class);
    }
}