<?php

namespace App\Dto;

use App\Enum\FormStatusLevel;
use App\Enum\FormStatusDataState;
use App\Enum\FormTrend;

final readonly class FormStatus
{
    public function __construct(
        public ?int $score,
        public ?FormStatusLevel $status,
        public FormTrend $trend,
        public FormStatusDataState $dataState,
        public int $recentPerformanceCount,
        public \DateTimeImmutable $calculatedAt,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'status' => $this->status?->value,
            'label' => $this->status?->label(),
            'dataState' => $this->dataState->value,
            'dataStateLabel' => $this->dataState->label(),
            'dataStateMessage' => $this->dataState->message(),
            'trend' => $this->trend->value,
            'trendLabel' => $this->trend->label(),
            'trendMessage' => $this->trend->message(),
            'recentPerformanceCount' => $this->recentPerformanceCount,
            'calculatedAt' => $this->calculatedAt->format(DATE_ATOM),
        ];
    }
}
