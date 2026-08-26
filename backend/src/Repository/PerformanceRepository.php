<?php

namespace App\Repository;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Performance>
 */
class PerformanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Performance::class);
    }

    /** @return list<Performance> */
    public function findBySession(Session $session): array
    {
        return $this->findBy(['session' => $session], ['createdAt' => 'DESC']);
    }

    /** @return list<Performance> */
    public function findUserPerformances(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    /** @return list<Performance> */
    public function findRecentPerformances(User $user, int $limit = 10): array
    {
        if ($limit <= 0) {
            throw new \InvalidArgumentException('La limite doit être strictement positive.');
        }

        return $this->createQueryBuilder('performance')
            ->innerJoin('performance.session', 'session')
            ->addSelect('session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->addSelect('plan')
            ->andWhere('performance.user = :user')
            ->setParameter('user', $user)
            ->orderBy('performance.createdAt', 'DESC')
            ->addOrderBy('performance.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneBySessionAndUser(Session $session, User $user): ?Performance
    {
        return $this->findOneBy(['session' => $session, 'user' => $user]);
    }

    /**
     * Historique complet, trié de la plus ancienne performance à la plus récente.
     *
     * @return list<Performance>
     */
    public function findByUser(User $user): array
    {
        return $this->ownedHistoryQuery($user)
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{date: string, value: float}> */
    public function findDistanceHistory(User $user): array
    {
        return $this->metricHistory($this->findByUser($user), 'distance');
    }

    /** @return list<array{date: string, value: int}> */
    public function findTimeHistory(User $user): array
    {
        return $this->metricHistory($this->findByUser($user), 'time');
    }

    /** @return list<array{date: string, value: int}> */
    public function findElevationHistory(User $user): array
    {
        return $this->metricHistory($this->findByUser($user), 'elevation');
    }

    /** @return list<Performance> */
    public function findByPeriod(
        User $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
    ): array {
        if ($start > $end) {
            throw new \InvalidArgumentException('La date de début doit précéder la date de fin.');
        }

        return $this->ownedHistoryQuery($user)
            ->andWhere('session.date BETWEEN :start AND :end')
            ->setParameter('start', $start, Types::DATE_IMMUTABLE)
            ->setParameter('end', $end, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getResult();
    }

    private function ownedHistoryQuery(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('performance')
            ->innerJoin('performance.session', 'session')
            ->addSelect('session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->addSelect('plan')
            ->andWhere('performance.user = :user')
            ->andWhere('plan.user = :user')
            ->setParameter('user', $user)
            ->orderBy('session.date', 'ASC')
            ->addOrderBy('performance.createdAt', 'ASC')
            ->addOrderBy('performance.id', 'ASC');
    }

    /**
     * @param list<Performance> $performances
     *
     * @return list<array{date: string, value: float|int}>
     */
    private function metricHistory(array $performances, string $metric): array
    {
        $history = [];

        foreach ($performances as $performance) {
            $date = $performance->getSession()?->getDate();
            $value = match ($metric) {
                'distance' => $performance->getDistanceKm(),
                'time' => $performance->getDurationSec(),
                'elevation' => $performance->getElevationDPlus(),
                default => throw new \LogicException('Métrique de performance inconnue.'),
            };

            if ($date !== null && $value !== null) {
                $history[] = [
                    'date' => $date->format('Y-m-d'),
                    'value' => $value,
                ];
            }
        }

        return $history;
    }
}
