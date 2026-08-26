<?php

namespace App\Repository;

use App\Entity\SessionIntensityZone;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SessionIntensityZone>
 */
class SessionIntensityZoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionIntensityZone::class);
    }

    /**
     * @return list<array{
     *     zoneId: int,
     *     name: string,
     *     occurrenceCount: int,
     *     durationPercentTotal: float
     * }>
     */
    public function findDistributionByUser(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        $query = $this->distributionQuery()
            ->andWhere('plan.user = :user')
            ->setParameter('user', $user);

        return $this->applyPeriod($query, $start, $end)->getQuery()->getArrayResult();
    }

    /**
     * @return list<array{
     *     zoneId: int,
     *     name: string,
     *     occurrenceCount: int,
     *     durationPercentTotal: float
     * }>
     */
    public function findDistributionByTrainingPlan(
        TrainingPlan $plan,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): array
    {
        $query = $this->distributionQuery()
            ->andWhere('session.trainingPlan = :plan')
            ->setParameter('plan', $plan);

        return $this->applyPeriod($query, $start, $end)->getQuery()->getArrayResult();
    }

    public function countDistinctSessionsByUser(
        User $user,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): int
    {
        $query = $this->createQueryBuilder('sessionZone')
            ->select('COUNT(DISTINCT session.id)')
            ->innerJoin('sessionZone.session', 'session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->andWhere('plan.user = :user')
            ->setParameter('user', $user);

        return (int) $this->applyPeriod($query, $start, $end)->getQuery()->getSingleScalarResult();
    }

    public function countDistinctSessionsByTrainingPlan(
        TrainingPlan $plan,
        ?\DateTimeInterface $start = null,
        ?\DateTimeInterface $end = null,
    ): int
    {
        $query = $this->createQueryBuilder('sessionZone')
            ->select('COUNT(DISTINCT session.id)')
            ->innerJoin('sessionZone.session', 'session')
            ->andWhere('session.trainingPlan = :plan')
            ->setParameter('plan', $plan);

        return (int) $this->applyPeriod($query, $start, $end)->getQuery()->getSingleScalarResult();
    }

    private function distributionQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('sessionZone')
            ->select('zone.id AS zoneId')
            ->addSelect('zone.name AS name')
            ->addSelect('COUNT(sessionZone.id) AS occurrenceCount')
            ->addSelect('SUM(sessionZone.durationPercent) AS durationPercentTotal')
            ->innerJoin('sessionZone.intensityZone', 'zone')
            ->innerJoin('sessionZone.session', 'session')
            ->innerJoin('session.trainingPlan', 'plan')
            ->groupBy('zone.id', 'zone.name')
            ->orderBy('zone.name', 'ASC');
    }

    private function applyPeriod(
        QueryBuilder $query,
        ?\DateTimeInterface $start,
        ?\DateTimeInterface $end,
    ): QueryBuilder {
        if (($start === null) !== ($end === null)) {
            throw new \InvalidArgumentException('Les deux bornes de la période sont obligatoires.');
        }

        if ($start !== null && $end !== null) {
            if ($start > $end) {
                throw new \InvalidArgumentException('La date de début doit précéder la date de fin.');
            }

            $query->andWhere('session.date BETWEEN :start AND :end')
                ->setParameter('start', $start, Types::DATE_IMMUTABLE)
                ->setParameter('end', $end, Types::DATE_IMMUTABLE);
        }

        return $query;
    }
}
