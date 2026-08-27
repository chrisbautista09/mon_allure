<?php

namespace App\Dto;

use App\Enum\PerformanceEvaluationResult;

final readonly class PerformanceEvaluation
{
    public function __construct(
        public PerformanceEvaluationResult $result,
        public float $distanceCompletionRate,
        public float $speedRatio,
        public ?float $heartRatePercent,
        public bool $targetIntensityRespected,
    ) {
    }

    /** @return array<string, bool|float|string|null> */
    public function toArray(): array
    {
        return [
            'result' => $this->result->value,
            'distanceCompletionRate' => $this->distanceCompletionRate,
            'speedRatio' => $this->speedRatio,
            'heartRatePercent' => $this->heartRatePercent,
            'targetIntensityRespected' => $this->targetIntensityRespected,
        ];
    }
}
