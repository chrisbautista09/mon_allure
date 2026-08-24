<?php

namespace App\Repository;

use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TrainingPlan> */
final class TrainingPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainingPlan::class);
    }

    public function findOneOwnedWithSessions(int $id, User $user): ?TrainingPlan
    {
        return $this->createQueryBuilder('plan')
            ->leftJoin('plan.sessions', 'session')
            ->addSelect('session')
            ->andWhere('plan.id = :id')
            ->andWhere('plan.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->orderBy('session.date', 'ASC')
            ->addOrderBy('session.id', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestActiveOwnedWithSessions(User $user): ?TrainingPlan
    {
        $plan = $this->findOneBy(
            ['user' => $user, 'isActive' => true],
            ['id' => 'DESC'],
        );

        return $plan instanceof TrainingPlan && $plan->getId() !== null
            ? $this->findOneOwnedWithSessions($plan->getId(), $user)
            : null;
    }
}
