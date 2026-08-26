<?php

namespace App\Repository;

use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /** @return Paginator<Session> */
    public function findPastSessionsByUser(
        User $user,
        int $page = 1,
        int $perPage = 20,
        ?\DateTimeImmutable $today = null,
        ?string $status = null,
        ?\DateTimeImmutable $fromDate = null,
        ?string $titleSearch = null,
    ): Paginator {
        if ($page < 1) {
            throw new \InvalidArgumentException('Le numéro de page doit être supérieur ou égal à 1.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new \InvalidArgumentException('Le nombre de séances par page doit être compris entre 1 et 100.');
        }

        $today ??= new \DateTimeImmutable('today');
        $query = $this->createQueryBuilder('session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->addSelect('plan')
            ->leftJoin('session.performance', 'performance')
            ->addSelect('performance')
            ->andWhere('plan.user = :user')
            ->andWhere('session.date <= :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today, Types::DATE_IMMUTABLE);

        if ($status !== null) {
            $query
                ->andWhere('session.status = :status')
                ->setParameter('status', $status);
        }

        if ($fromDate !== null) {
            $query
                ->andWhere('session.date >= :fromDate')
                ->setParameter('fromDate', $fromDate, Types::DATE_IMMUTABLE);
        }

        if ($titleSearch !== null && trim($titleSearch) !== '') {
            $query
                ->andWhere('LOWER(session.title) LIKE :titleSearch')
                ->setParameter('titleSearch', '%'.mb_strtolower(trim($titleSearch)).'%');
        }

        $query
            ->orderBy('session.date', 'DESC')
            ->addOrderBy('session.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query, fetchJoinCollection: false);
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
