<?php

namespace App\Repository;

use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TrainingPlan> */
class TrainingPlanRepository extends ServiceEntityRepository
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
        $plan = $this->findLatestActiveOwned($user);

        return $plan instanceof TrainingPlan && $plan->getId() !== null
            ? $this->findOneOwnedWithSessions($plan->getId(), $user)
            : null;
    }

    public function findLatestActiveOwned(User $user): ?TrainingPlan
    {
        return $this->findOneBy(
            ['user' => $user, 'isActive' => true],
            ['id' => 'DESC'],
        );
    }

    /** @return Paginator<TrainingPlan> */
    public function findAllForAdmin(int $page = 1, int $perPage = 20): Paginator
    {
        return $this->paginate($this->administrationQuery(), $page, $perPage);
    }

    /** @return Paginator<TrainingPlan> */
    public function findByFeasibilityStatus(string $status, int $page = 1, int $perPage = 20): Paginator
    {
        $status = strtoupper(trim($status));
        if ($status === '') {
            throw new \InvalidArgumentException('Le statut de faisabilité est obligatoire.');
        }

        return $this->paginate(
            $this->administrationQuery()
                ->andWhere('plan.feasibilityIndicator = :status')
                ->setParameter('status', $status),
            $page,
            $perPage,
        );
    }

    /** @return Paginator<TrainingPlan> */
    public function findByUser(User $user, int $page = 1, int $perPage = 20): Paginator
    {
        return $this->paginate(
            $this->administrationQuery()
                ->andWhere('plan.user = :user')
                ->setParameter('user', $user),
            $page,
            $perPage,
        );
    }

    /** @return Paginator<TrainingPlan> */
    public function findProblematicPlans(int $page = 1, int $perPage = 20): Paginator
    {
        return $this->paginate(
            $this->administrationQuery()
                ->andWhere('plan.feasibilityIndicator IN (:impossible) OR plan.progressScore < :minimumScore')
                ->setParameter('impossible', ['FAIBLE', 'IMPOSSIBLE'])
                ->setParameter('minimumScore', 30),
            $page,
            $perPage,
        );
    }

    /** @return Paginator<TrainingPlan> */
    public function searchForAdmin(
        string $feasibility = 'ALL',
        string $userSearch = '',
        string $status = 'ALL',
        int $page = 1,
        int $perPage = 20,
    ): Paginator {
        $feasibility = strtoupper(trim($feasibility));
        $status = strtoupper(trim($status));
        $userSearch = trim($userSearch);
        $feasibilityValues = match ($feasibility) {
            'ALL' => null,
            'OPTIMAL' => ['OPTIMAL'],
            'ACCEPTABLE' => ['BON', 'MOYEN', 'ACCEPTABLE'],
            'IMPOSSIBLE' => ['FAIBLE', 'IMPOSSIBLE'],
            default => throw new \InvalidArgumentException('Le filtre de faisabilité est invalide.'),
        };
        if (!in_array($status, ['ALL', 'ACTIVE', 'COMPLETED', 'ARCHIVED'], true)) {
            throw new \InvalidArgumentException('Le filtre de statut du plan est invalide.');
        }

        $query = $this->administrationQuery();
        if ($feasibilityValues !== null) {
            $query->andWhere('plan.feasibilityIndicator IN (:feasibility)')
                ->setParameter('feasibility', $feasibilityValues);
        }
        if ($userSearch !== '') {
            $query->andWhere('LOWER(user.pseudo) LIKE :userSearch OR LOWER(user.email) LIKE :userSearch')
                ->setParameter('userSearch', '%'.strtolower($userSearch).'%');
        }

        $today = new \DateTimeImmutable('today');
        if ($status === 'ACTIVE') {
            $query->andWhere('plan.isActive = :active')
                ->andWhere('plan.endDate >= :today')
                ->setParameter('active', true)
                ->setParameter('today', $today);
        } elseif ($status === 'COMPLETED') {
            $query->andWhere('plan.endDate < :today')->setParameter('today', $today);
        } elseif ($status === 'ARCHIVED') {
            $query->andWhere('plan.isActive = :active')
                ->andWhere('plan.endDate >= :today')
                ->setParameter('active', false)
                ->setParameter('today', $today);
        }

        return $this->paginate($query, $page, $perPage);
    }

    private function administrationQuery(): QueryBuilder
    {
        return $this->createQueryBuilder('plan')
            ->innerJoin('plan.user', 'user')
            ->addSelect('user')
            ->leftJoin('plan.sessions', 'session')
            ->addSelect('session')
            ->orderBy('plan.createdAt', 'DESC')
            ->addOrderBy('plan.id', 'DESC');
    }

    /** @return Paginator<TrainingPlan> */
    private function paginate(QueryBuilder $query, int $page, int $perPage): Paginator
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Le numéro de page doit être supérieur ou égal à 1.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new \InvalidArgumentException('Le nombre de plans par page doit être compris entre 1 et 100.');
        }

        $query->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($query->getQuery(), fetchJoinCollection: true);
    }
}
