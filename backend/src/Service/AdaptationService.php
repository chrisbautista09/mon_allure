<?php

namespace App\Service;

use App\Dto\AdaptationResult;
use App\Entity\AlgorithmParameter;
use App\Entity\Performance;
use App\Entity\Session;
use App\Enum\AdaptationDecision;
use App\Enum\PerformanceEvaluationResult;
use App\Repository\SessionRepository;

final class AdaptationService
{
    public function __construct(
        private readonly PerformanceEvaluationService $evaluationService,
        private readonly ProgressScoreCalculatorService $progressScoreCalculator,
        private readonly SessionGeneratorService $sessionGenerator,
        private readonly SessionRepository $sessionRepository,
        private readonly AlgorithmParameterService $parameterService,
    ) {
    }

    public function adapt(Performance $performance): AdaptationResult
    {
        $plan = $performance->getSession()?->getTrainingPlan();

        if ($plan === null) {
            throw new \InvalidArgumentException('La performance doit être rattachée à un plan d’entraînement.');
        }

        $evaluation = $this->evaluationService->evaluate($performance);
        [$decision, $loadFactor] = match ($evaluation->result) {
            PerformanceEvaluationResult::SUPERIOR => [
                AdaptationDecision::INCREASE,
                1.05,
            ],
            PerformanceEvaluationResult::INSUFFICIENT => [
                AdaptationDecision::REDUCE,
                0.95,
            ],
            PerformanceEvaluationResult::OK => [
                AdaptationDecision::MAINTAIN,
                1.0,
            ],
        };
        $previousScore = $plan->getProgressScore();
        $newScore = $this->progressScoreCalculator->calculate($performance, $evaluation);
        $plan->setProgressScore($newScore);
        $adjustedSessions = [];
        $successRate = null;
        $successValidationRate = null;
        $adaptationReason = null;
        $adaptationModification = null;

        if ($this->isCompleteThreeWeekBlock($performance)) {
            $currentSession = $performance->getSession();
            $currentDate = $currentSession?->getDate();

            if ($currentSession === null || $currentDate === null) {
                throw new \LogicException('La séance courante doit posséder une date.');
            }

            $periodStart = $currentDate->modify('-3 weeks +1 day');
            $recentSessions = $this->sessionRepository->findRecentForPlan($plan, $currentDate, 3);
            $successRate = $this->calculateSuccessRate(
                $recentSessions,
                $periodStart,
                $currentDate,
                $currentDate->modify('+1 day'),
            );
            $parameters = $this->parameterService->getCurrentParameters();
            $successValidationRate = $parameters[AlgorithmParameter::KEY_SUCCESS_VALIDATION_RATE];
            if ($successRate >= $successValidationRate) {

                $decision = AdaptationDecision::INCREASE;
                $loadFactor = 1 + ($parameters[AlgorithmParameter::KEY_PROGRESSION_MAX_PERCENT] / 100);
            } else {
                $currentWeek = $currentSession->getWeekIndex();

                if ($currentWeek === null) {
                    throw new \LogicException('La séance courante doit appartenir à une semaine du plan.');
                }

                $recoveryFrequency = (int) round($parameters[AlgorithmParameter::KEY_RECOVERY_WEEK_FREQUENCY]);
                $isRecoveryDue = ($currentWeek + 1) % $recoveryFrequency === 0;
                [$decision, $loadFactor] = $isRecoveryDue
                    ? [AdaptationDecision::REDUCE, 0.90]
                    : [AdaptationDecision::MAINTAIN, 1.0];
            }
            if ($decision !== AdaptationDecision::MAINTAIN) {
                $adjustedSessions = $this->sessionGenerator->regenerateUpcoming(
                    $plan,
                    $currentSession,
                    $loadFactor,
                );
            }

            $history = $this->createHistory(
                $successRate,
                $successValidationRate,
                $decision,
                $loadFactor,
            );
            $plan->addAdaptationHistory($history);
            $adaptationReason = $history['reason'];
            $adaptationModification = $history['modification'];
        }

        return new AdaptationResult(
            $evaluation,
            $decision,
            $loadFactor,
            $previousScore,
            $newScore,
            count($adjustedSessions),
            $successRate,
            $successValidationRate,
            $adaptationReason,
            $adaptationModification,
        );
    }

    /**
     * @param iterable<Session> $sessions
     */
    public function calculateSuccessRate(
        iterable $sessions,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        ?\DateTimeImmutable $today = null,
    ): float {
        if ($periodEnd < $periodStart) {
            throw new \InvalidArgumentException('La fin de la période doit être postérieure à son début.');
        }

        $today = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $periodStart = $periodStart->setTime(0, 0);
        $periodEnd = $periodEnd->setTime(0, 0);
        $plannedSessions = 0;
        $successfulSessions = 0;

        foreach ($sessions as $session) {
            $date = $session->getDate()?->setTime(0, 0);

            if ($date === null || $date < $periodStart || $date > $periodEnd || $date >= $today) {
                continue;
            }

            ++$plannedSessions;

            if ($session->isSuccessful()) {
                ++$successfulSessions;
            }
        }

        if ($plannedSessions === 0) {
            return 0.0;
        }

        return round(($successfulSessions / $plannedSessions) * 100, 2);
    }

    public function meetsSuccessValidationRate(
        float $successRate,
        AlgorithmParameter $successValidationRate,
    ): bool {
        if ($successRate < 0 || $successRate > 100) {
            throw new \InvalidArgumentException('Le taux de réussite doit être compris entre 0 et 100.');
        }

        return $successRate >= $successValidationRate->getSuccessValidationRate();
    }

    private function isCompleteThreeWeekBlock(Performance $performance): bool
    {
        $currentSession = $performance->getSession();
        $plan = $currentSession?->getTrainingPlan();
        $currentWeek = $currentSession?->getWeekIndex();

        if ($plan === null || $currentWeek === null || $currentWeek % 3 !== 0) {
            return false;
        }

        $firstWeek = $currentWeek - 2;
        $blockSessions = array_filter(
            $plan->getSessions()->toArray(),
            static fn ($session): bool => $session->getWeekIndex() !== null
                && $session->getWeekIndex() >= $firstWeek
                && $session->getWeekIndex() <= $currentWeek,
        );

        if ($blockSessions === []) {
            return false;
        }

        foreach ($blockSessions as $session) {
            if ($session === $currentSession) {
                continue;
            }

            if (!in_array($session->getStatus(), ['completed', 'missed', 'cancelled'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     adaptedAt: string,
     *     successRate: float,
     *     successValidationRate: float,
     *     decision: string,
     *     loadFactor: float,
     *     reason: string,
     *     modification: string
     * }
     */
    private function createHistory(
        float $successRate,
        float $successValidationRate,
        AdaptationDecision $decision,
        float $loadFactor,
    ): array {
        $comparison = $successRate >= $successValidationRate ? 'atteint' : 'inférieur à';
        $reason = sprintf(
            'Taux de réussite de %.2f %% %s au seuil de %.2f %%.',
            $successRate,
            $comparison,
            $successValidationRate,
        );
        $modification = match ($decision) {
            AdaptationDecision::INCREASE => sprintf('Charge augmentée de %.2f %%.', ($loadFactor - 1) * 100),
            AdaptationDecision::REDUCE => sprintf('Charge réduite de %.2f %%.', (1 - $loadFactor) * 100),
            AdaptationDecision::MAINTAIN => 'Charge actuelle maintenue.',
        };

        return [
            'adaptedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'successRate' => $successRate,
            'successValidationRate' => $successValidationRate,
            'decision' => $decision->value,
            'loadFactor' => $loadFactor,
            'reason' => $reason,
            'modification' => $modification,
        ];
    }
}
