<?php

namespace App\Service;

use App\Dto\PerformanceEvaluation;
use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Enum\PerformanceEvaluationResult;
use App\Repository\IntensityZoneRepository;

final class PerformanceEvaluationService
{
    private const float TOLERANCE = 0.10;
    private const float SUPERIOR_THRESHOLD = 1.05;
    private const float HEART_RATE_TOLERANCE_PERCENT = 5.0;

    public function __construct(private readonly IntensityZoneRepository $zoneRepository)
    {
    }

    public function evaluate(Performance $performance): PerformanceEvaluation
    {
        $session = $performance->getSession();
        $profile = $performance->getUser()?->getProfile();
        $plannedDistance = $session?->getPlannedDistanceKm();
        $plannedDuration = $session?->getPlannedDurationMin();
        $actualDistance = $performance->getDistanceKm();
        $actualDuration = $performance->getDurationSec();

        if ($session === null
            || $plannedDistance === null || $plannedDistance <= 0
            || $plannedDuration === null || $plannedDuration <= 0
            || $actualDistance === null || $actualDistance <= 0
            || $actualDuration === null || $actualDuration <= 0) {
            throw new \InvalidArgumentException('Les données prévues et réalisées doivent être complètes et positives.');
        }

        $plannedSpeed = $plannedDistance / ($plannedDuration / 60);
        $actualSpeed = $actualDistance / ($actualDuration / 3600);
        $distanceCompletionRate = $actualDistance / $plannedDistance;
        $speedRatio = $actualSpeed / $plannedSpeed;
        [$heartRatePercent, $targetIntensityRespected, $intensityTooHigh] = $this->evaluateIntensity($performance);

        $result = match (true) {
            $distanceCompletionRate < 1 - self::TOLERANCE,
            $speedRatio < 1 - self::TOLERANCE,
            $intensityTooHigh => PerformanceEvaluationResult::INSUFFICIENT,
            $distanceCompletionRate >= 1.0
                && $speedRatio >= self::SUPERIOR_THRESHOLD
                && $targetIntensityRespected => PerformanceEvaluationResult::SUPERIOR,
            default => PerformanceEvaluationResult::OK,
        };

        return new PerformanceEvaluation(
            $result,
            round($distanceCompletionRate, 4),
            round($speedRatio, 4),
            $heartRatePercent === null ? null : round($heartRatePercent, 2),
            $targetIntensityRespected,
        );
    }

    /** @return array{?float, bool, bool} */
    private function evaluateIntensity(Performance $performance): array
    {
        $averageHeartRate = $performance->getAvgHr();
        $maximumHeartRate = $performance->getUser()?->getProfile()?->getFcm();
        $zoneName = $performance->getSession()?->getPlannedFcmZone();

        if ($averageHeartRate === null || $maximumHeartRate === null || $maximumHeartRate <= 0 || $zoneName === null) {
            return [null, true, false];
        }

        $zone = $this->zoneRepository->findOneBy(['name' => $zoneName]);

        if (!$zone instanceof IntensityZone
            || $zone->getFcmPercentMin() === null
            || $zone->getFcmPercentMax() === null) {
            throw new \LogicException(sprintf('La zone d’intensité %s est introuvable ou incomplète.', $zoneName));
        }

        $heartRatePercent = $averageHeartRate / $maximumHeartRate * 100;
        $minimum = $zone->getFcmPercentMin() - self::HEART_RATE_TOLERANCE_PERCENT;
        $maximum = $zone->getFcmPercentMax() + self::HEART_RATE_TOLERANCE_PERCENT;

        return [
            $heartRatePercent,
            $heartRatePercent >= $minimum && $heartRatePercent <= $maximum,
            $heartRatePercent > $maximum,
        ];
    }
}
