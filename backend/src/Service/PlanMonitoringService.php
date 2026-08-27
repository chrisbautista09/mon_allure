<?php

namespace App\Service;

use App\Entity\AlgorithmParameter;
use App\Entity\TrainingPlan;
use App\Repository\TrainingPlanRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PlanMonitoringService
{
    public const ANOMALY_IMPOSSIBLE_FEASIBILITY = 'IMPOSSIBLE_FEASIBILITY';
    public const ANOMALY_LOW_PROGRESS = 'LOW_PROGRESS';
    public const ANOMALY_INCONSISTENT_DURATION = 'INCONSISTENT_DURATION';
    public const ANOMALY_EXCESSIVE_TRAINING_LOAD = 'EXCESSIVE_TRAINING_LOAD';
    public const ANOMALY_LOW_SUCCESS_RATE = 'LOW_SUCCESS_RATE';

    public function __construct(
        private readonly TrainingPlanRepository $trainingPlanRepository,
        private readonly AlgorithmParameterService $parameterService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     plans: list<array<string, mixed>>,
     *     pagination: array{page: int, perPage: int, total: int, pages: int}
     * }
     */
    public function getPlansOverview(
        int $page = 1,
        int $perPage = 20,
        string $feasibility = 'ALL',
        string $userSearch = '',
        string $status = 'ALL',
    ): array
    {
        $plans = $this->trainingPlanRepository->searchForAdmin(
            $feasibility,
            $userSearch,
            $status,
            $page,
            $perPage,
        );
        $parameters = $this->parameterService->getCurrentParameters();
        $items = [];

        foreach ($plans as $plan) {
            $items[] = $this->analyze($plan, $parameters);
        }
        $this->entityManager->flush();

        $total = count($plans);

        return [
            'plans' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function analyzePlan(TrainingPlan $plan): array
    {
        $analysis = $this->analyze($plan, $this->parameterService->getCurrentParameters());
        $this->entityManager->flush();

        return $analysis;
    }

    /** @return list<string> */
    public function detectAnomalies(TrainingPlan $plan): array
    {
        $parameters = $this->parameterService->getCurrentParameters();
        $metrics = $this->sessionMetrics($plan);

        return $this->anomalies($plan, $metrics, $parameters);
    }

    /**
     * @param array<string, float> $parameters
     * @return array<string, mixed>
     */
    private function analyze(TrainingPlan $plan, array $parameters): array
    {
        $metrics = $this->sessionMetrics($plan);
        $anomalies = $this->anomalies($plan, $metrics, $parameters);
        $plan->synchronizeMonitoringHistory($this->anomalyDescriptions($anomalies));

        return [
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'user' => [
                'id' => $plan->getUser()?->getId(),
                'email' => $plan->getUser()?->getEmail(),
                'pseudo' => $plan->getUser()?->getPseudo(),
            ],
            'feasibilityIndicator' => $plan->getFeasibilityIndicator(),
            'progressScore' => $plan->getProgressScore(),
            'objective' => [
                'type' => $plan->getTargetType(),
                'value' => $plan->getTargetValue(),
                'unit' => $plan->getTargetUnit(),
                'terrainType' => $plan->getTerrainType(),
            ],
            'durationWeeks' => $plan->getDurationWeeks(),
            'currentWeek' => $plan->getCurrentWeek(),
            'isActive' => $plan->isActive(),
            'status' => $this->monitoringStatus($plan),
            'startDate' => $plan->getStartDate()?->format('Y-m-d'),
            'endDate' => $plan->getEndDate()?->format('Y-m-d'),
            'createdAt' => $plan->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'trainingLoad' => [
                'sessionCount' => $metrics['sessionCount'],
                'plannedDurationMinutes' => $metrics['plannedDurationMinutes'],
                'plannedDistanceKm' => $metrics['plannedDistanceKm'],
                'maximumSessionsPerWeek' => $metrics['maximumSessionsPerWeek'],
            ],
            'successRate' => $metrics['successRate'],
            'completedSessions' => $metrics['completedSessions'],
            'terminalSessions' => $metrics['terminalSessions'],
            'isProblematic' => $anomalies !== [],
            'anomalyCount' => count($anomalies),
            'anomalies' => $anomalies,
            'monitoringHistory' => $plan->getMonitoringHistory(),
        ];
    }

    private function monitoringStatus(TrainingPlan $plan): string
    {
        $endDate = $plan->getEndDate();
        if ($endDate !== null && $endDate < new \DateTimeImmutable('today')) {
            return 'COMPLETED';
        }

        return $plan->isActive() ? 'ACTIVE' : 'ARCHIVED';
    }

    /**
     * @return array{
     *     sessionCount: int,
     *     plannedDurationMinutes: int,
     *     plannedDistanceKm: float,
     *     maximumSessionsPerWeek: int,
     *     completedSessions: int,
     *     terminalSessions: int,
     *     successRate: float
     * }
     */
    private function sessionMetrics(TrainingPlan $plan): array
    {
        $sessionCount = 0;
        $plannedDurationMinutes = 0;
        $plannedDistanceKm = 0.0;
        $completedSessions = 0;
        $terminalSessions = 0;
        $sessionsPerWeek = [];

        foreach ($plan->getSessions() as $session) {
            ++$sessionCount;
            $plannedDurationMinutes += $session->getPlannedDurationMin() ?? 0;
            $plannedDistanceKm += $session->getPlannedDistanceKm() ?? 0.0;
            $week = $session->getWeekIndex();
            if ($week !== null) {
                $sessionsPerWeek[$week] = ($sessionsPerWeek[$week] ?? 0) + 1;
            }
            if ($session->isTerminal()) {
                ++$terminalSessions;
            }
            if ($session->isSuccessful()) {
                ++$completedSessions;
            }
        }

        return [
            'sessionCount' => $sessionCount,
            'plannedDurationMinutes' => $plannedDurationMinutes,
            'plannedDistanceKm' => round($plannedDistanceKm, 2),
            'maximumSessionsPerWeek' => $sessionsPerWeek === [] ? 0 : max($sessionsPerWeek),
            'completedSessions' => $completedSessions,
            'terminalSessions' => $terminalSessions,
            'successRate' => $terminalSessions === 0
                ? 0.0
                : round(($completedSessions / $terminalSessions) * 100, 2),
        ];
    }

    /**
     * @param array<string, int|float> $metrics
     * @param array<string, float> $parameters
     * @return list<string>
     */
    private function anomalies(TrainingPlan $plan, array $metrics, array $parameters): array
    {
        $anomalies = [];

        if (in_array($plan->getFeasibilityIndicator(), ['FAIBLE', 'IMPOSSIBLE'], true)) {
            $anomalies[] = self::ANOMALY_IMPOSSIBLE_FEASIBILITY;
        }
        if ($plan->getProgressScore() < 30) {
            $anomalies[] = self::ANOMALY_LOW_PROGRESS;
        }
        if ($plan->getDurationWeeks() === null || $plan->getCurrentWeek() > $plan->getDurationWeeks()) {
            $anomalies[] = self::ANOMALY_INCONSISTENT_DURATION;
        }

        $sessionLimitKey = match ($plan->getPoleType()) {
            'discovery' => AlgorithmParameter::KEY_MAX_SESSIONS_DISCOVERY,
            'intermediate' => AlgorithmParameter::KEY_MAX_SESSIONS_INTERMEDIATE,
            'performance' => AlgorithmParameter::KEY_MAX_SESSIONS_PERFORMANCE,
            default => null,
        };
        if (
            $sessionLimitKey !== null
            && isset($parameters[$sessionLimitKey])
            && $metrics['maximumSessionsPerWeek'] > $parameters[$sessionLimitKey]
        ) {
            $anomalies[] = self::ANOMALY_EXCESSIVE_TRAINING_LOAD;
        }

        $successThreshold = $parameters[AlgorithmParameter::KEY_SUCCESS_VALIDATION_RATE] ?? 80.0;
        if ($metrics['terminalSessions'] > 0 && $metrics['successRate'] < $successThreshold) {
            $anomalies[] = self::ANOMALY_LOW_SUCCESS_RATE;
        }

        return $anomalies;
    }

    /**
     * @param list<string> $anomalies
     * @return array<string, string>
     */
    private function anomalyDescriptions(array $anomalies): array
    {
        $descriptions = [
            self::ANOMALY_IMPOSSIBLE_FEASIBILITY => 'La faisabilité du plan est critique.',
            self::ANOMALY_LOW_PROGRESS => 'Le score de progression est inférieur à 30 %.',
            self::ANOMALY_INCONSISTENT_DURATION => 'La semaine actuelle dépasse la durée du plan.',
            self::ANOMALY_EXCESSIVE_TRAINING_LOAD => 'La charge hebdomadaire dépasse la limite du pôle.',
            self::ANOMALY_LOW_SUCCESS_RATE => 'Le taux de réussite est inférieur au seuil configuré.',
        ];

        return array_combine(
            $anomalies,
            array_map(static fn (string $type): string => $descriptions[$type], $anomalies),
        );
    }
}
