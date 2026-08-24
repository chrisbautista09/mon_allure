<?php

namespace App\Service;

use App\Dto\AdaptationResult;
use App\Entity\Performance;
use App\Enum\AdaptationDecision;
use App\Enum\PerformanceEvaluationResult;

final class AdaptationService
{
    public function __construct(
        private readonly PerformanceEvaluationService $evaluationService,
        private readonly ProgressScoreCalculatorService $progressScoreCalculator,
        private readonly SessionGeneratorService $sessionGenerator,
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
        $adjustedSessions = $this->isCompleteThreeWeekBlock($performance)
            ? $this->sessionGenerator->recalibrateFutureSessions(
                $plan,
                $performance->getSession(),
                $loadFactor,
            )
            : [];

        return new AdaptationResult(
            $evaluation,
            $decision,
            $loadFactor,
            $previousScore,
            $newScore,
            count($adjustedSessions),
        );
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

            if (!in_array($session->getStatus(), ['done', 'missed', 'partially_done'], true)) {
                return false;
            }
        }

        return true;
    }
}
