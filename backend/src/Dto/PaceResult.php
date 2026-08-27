<?php

namespace App\Dto;

final readonly class PaceResult
{
    public function __construct(
        public float $speed,
        public int $paceSecondsPerKm,
        public string $pace,
        public float $vmaPercent,
        public string $heartRateZone,
        public int $heartRateMin,
        public int $heartRateMax,
        public float $fcmPercentMin,
        public float $fcmPercentMax,
    ) {
    }

    /** @return array<string, float|int|string> */
    public function toArray(): array
    {
        return [
            'speed' => $this->speed,
            'paceSecondsPerKm' => $this->paceSecondsPerKm,
            'pace' => $this->pace,
            'vmaPercent' => $this->vmaPercent,
            'heartRateZone' => $this->heartRateZone,
            'heartRateMin' => $this->heartRateMin,
            'heartRateMax' => $this->heartRateMax,
            'fcmPercentMin' => $this->fcmPercentMin,
            'fcmPercentMax' => $this->fcmPercentMax,
        ];
    }
}
