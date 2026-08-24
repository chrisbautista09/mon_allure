<?php

namespace App\Tests\Unit\Service;

use App\Entity\IntensityZone;
use App\Entity\Performance;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\PerformanceEvaluationResult;
use App\Repository\IntensityZoneRepository;
use App\Service\PerformanceEvaluationService;
use PHPUnit\Framework\TestCase;

final class PerformanceEvaluationServiceTest extends TestCase
{
    public function testReturnsOkWhenActualPerformanceMatchesPrediction(): void
    {
        $evaluation = $this->service()->evaluate($this->performance(10, 3600, 130));

        self::assertSame(PerformanceEvaluationResult::OK, $evaluation->result);
        self::assertSame(1.0, $evaluation->distanceCompletionRate);
        self::assertSame(1.0, $evaluation->speedRatio);
        self::assertTrue($evaluation->targetIntensityRespected);
    }

    public function testReturnsSuperiorWhenRunnerIsFasterAtControlledIntensity(): void
    {
        $evaluation = $this->service()->evaluate($this->performance(10.5, 3600, 130));

        self::assertSame(PerformanceEvaluationResult::SUPERIOR, $evaluation->result);
        self::assertSame(1.05, $evaluation->distanceCompletionRate);
        self::assertSame(1.05, $evaluation->speedRatio);
    }

    public function testReturnsInsufficientWhenVolumeOrSpeedIsTooLow(): void
    {
        $evaluation = $this->service()->evaluate($this->performance(8.5, 3600, 130));

        self::assertSame(PerformanceEvaluationResult::INSUFFICIENT, $evaluation->result);
        self::assertSame(0.85, $evaluation->distanceCompletionRate);
    }

    public function testReturnsInsufficientWhenTargetRequiresExcessiveHeartRate(): void
    {
        $evaluation = $this->service()->evaluate($this->performance(10, 3600, 170));

        self::assertSame(PerformanceEvaluationResult::INSUFFICIENT, $evaluation->result);
        self::assertFalse($evaluation->targetIntensityRespected);
        self::assertGreaterThan(75, $evaluation->heartRatePercent);
    }

    public function testRejectsIncompletePrediction(): void
    {
        $performance = $this->performance(10, 3600, 130);
        $performance->getSession()?->setPlannedDistanceKm(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->evaluate($performance);
    }

    private function service(): PerformanceEvaluationService
    {
        $zone = (new IntensityZone())
            ->setName('Z2')
            ->setVmaCoefMin(0.65)
            ->setVmaCoefMax(0.75)
            ->setFcmPercentMin(60)
            ->setFcmPercentMax(70);
        $repository = $this->createStub(IntensityZoneRepository::class);
        $repository->method('findOneBy')->willReturn($zone);

        return new PerformanceEvaluationService($repository);
    }

    private function performance(float $distanceKm, int $durationSec, int $averageHeartRate): Performance
    {
        $user = new User();
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190)
            ->setUser($user);
        $user->setProfile($profile);
        $session = (new Session())
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setPlannedFcmZone('Z2');

        return (new Performance())
            ->setUser($user)
            ->setSession($session)
            ->setDistanceKm($distanceKm)
            ->setDurationSec($durationSec)
            ->setAvgHr($averageHeartRate);
    }
}
