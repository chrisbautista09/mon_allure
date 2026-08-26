<?php

namespace App\Repository;

use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    public function findOneOwned(int $id, User $user): ?Session
    {
        return $this->createQueryBuilder('session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->addSelect('plan')
            ->andWhere('session.id = :id')
            ->andWhere('plan.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Session> */
    public function findRecentForPlan(
        TrainingPlan $plan,
        \DateTimeImmutable $until,
        int $weeks = 3,
    ): array {
        if ($weeks <= 0) {
            throw new \InvalidArgumentException('Le nombre de semaines doit être supérieur à zéro.');
        }

        $from = $until->modify(sprintf('-%d weeks +1 day', $weeks));

        return $this->createQueryBuilder('session')
            ->andWhere('session.trainingPlan = :plan')
            ->andWhere('session.date BETWEEN :from AND :until')
            ->setParameter('plan', $plan)
            ->setParameter('from', $from, Types::DATE_IMMUTABLE)
            ->setParameter('until', $until, Types::DATE_IMMUTABLE)
            ->orderBy('session.date', 'ASC')
            ->addOrderBy('session.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
