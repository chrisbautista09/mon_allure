<?php

namespace App\Dto;

use App\Enum\AdaptationDecision;

final readonly class AdaptationResult
{
    public function __construct(
        public PerformanceEvaluation $evaluation,
        public AdaptationDecision $decision,
        public float $loadFactor,
        public float $previousProgressScore,
        public float $progressScore,
        public int $adjustedSessionsCount,
    ) {
    }

    /** @return array<string, array<string, bool|float|string|null>|float|string> */
    public function toArray(): array
    {
        return [
            'decision' => $this->decision->value,
            'loadFactor' => $this->loadFactor,
            'previousProgressScore' => $this->previousProgressScore,
            'progressScore' => $this->progressScore,
            'adjustedSessionsCount' => $this->adjustedSessionsCount,
            'evaluation' => $this->evaluation->toArray(),
        ];
    }
}
