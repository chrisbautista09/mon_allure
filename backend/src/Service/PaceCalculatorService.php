<?php

namespace App\Service;

use App\Dto\PaceResult;
use App\Entity\AlgorithmParameter;
use App\Entity\IntensityZone;
use App\Entity\Profile;

class PaceCalculatorService
{
    public function __construct(private readonly ?AlgorithmParameterService $parameterService = null)
    {
    }

    public function calculate(
        Profile $profile,
        IntensityZone $zone,
        ?float $vmaCoefficient = null,
    ): PaceResult {
        $vma = $profile->getVma();
        $fcm = $profile->getFcm();

        if ($vma === null || $vma <= 0) {
            throw new \DomainException('La VMA du profil est requise pour calculer une allure.');
        }

        if ($fcm === null || $fcm <= 0) {
            throw new \DomainException('La FCM du profil est requise pour calculer une zone cardiaque.');
        }

        $zoneName = $zone->getName();
        $vmaMin = $zone->getVmaCoefMin();
        $vmaMax = $zone->getVmaCoefMax();
        $fcmMin = $zone->getFcmPercentMin();
        $fcmMax = $zone->getFcmPercentMax();

        if ($zoneName === null
            || $vmaMin === null || $vmaMax === null
            || $fcmMin === null || $fcmMax === null
            || $vmaMin <= 0 || $vmaMax < $vmaMin
            || $fcmMin <= 0 || $fcmMax < $fcmMin || $fcmMax > 100) {
            throw new \InvalidArgumentException('La zone d’intensité est incomplète ou incohérente.');
        }

        $coefficient = $vmaCoefficient
            ?? $this->configuredCoefficient($zoneName)
            ?? (($vmaMin + $vmaMax) / 2);

        if ($coefficient < $vmaMin || $coefficient > $vmaMax) {
            throw new \InvalidArgumentException(sprintf(
                'Le coefficient VMA doit être compris entre %.2f et %.2f pour la zone %s.',
                $vmaMin,
                $vmaMax,
                $zoneName
            ));
        }

        $speed = round($vma * $coefficient, 2);
        $paceSeconds = (int) round(3600 / $speed);

        return new PaceResult(
            speed: $speed,
            paceSecondsPerKm: $paceSeconds,
            pace: $this->formatPace($paceSeconds),
            vmaPercent: round($coefficient * 100, 1),
            heartRateZone: $zoneName,
            heartRateMin: (int) round($fcm * $fcmMin / 100),
            heartRateMax: (int) round($fcm * $fcmMax / 100),
            fcmPercentMin: $fcmMin,
            fcmPercentMax: $fcmMax,
        );
    }

    private function configuredCoefficient(string $zoneName): ?float
    {
        if ($this->parameterService === null) {
            return null;
        }

        $key = match ($zoneName) {
            'Z2' => AlgorithmParameter::KEY_COEF_ENDURANCE,
            'Z3' => AlgorithmParameter::KEY_COEF_ACTIVE,
            'Z4' => AlgorithmParameter::KEY_COEF_THRESHOLD,
            'Z5' => AlgorithmParameter::KEY_COEF_VMA,
            default => null,
        };

        return $key === null
            ? null
            : ($this->parameterService->getCurrentParameters()[$key] ?? null);
    }

    private function formatPace(int $secondsPerKm): string
    {
        return sprintf(
            '%d:%02d/km',
            intdiv($secondsPerKm, 60),
            $secondsPerKm % 60
        );
    }
}
