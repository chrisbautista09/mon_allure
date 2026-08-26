<?php

namespace App\Repository;

use App\Entity\AlgorithmParameter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlgorithmParameter>
 */
class AlgorithmParameterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlgorithmParameter::class);
    }
}
