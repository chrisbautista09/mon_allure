<?php

namespace App\Tests\Unit\Service;

use App\Entity\Profile;
use App\Entity\TrainingPlan;
use App\Enum\FeasibilityLevel;
use App\Service\FeasibilityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FeasibilityServiceTest extends TestCase
{
    private FeasibilityService $service;

    protected function setUp(): void
    {
        $this->service = new FeasibilityService();
    }

    public function testPlanShorterThanMinimumIsAlwaysLow(): void
    {
        $result = $this->service->evaluate(
            $this->profile(18, 60),
            $this->distancePlan(5, 6),
            8
        );

        self::assertSame(FeasibilityLevel::LOW, $result);
    }

    #[DataProvider('feasibilityProvider')]
    public function testEvaluatesRunnerLevelGoalAndAvailableDuration(
        float $vma,
        ?float $vo2max,
        float $distanceKm,
        int $durationWeeks,
        FeasibilityLevel $expected,
    ): void {
        $result = $this->service->evaluate(
            $this->profile($vma, $vo2max),
            $this->distancePlan($distanceKm, $durationWeeks),
            8
        );

        self::assertSame($expected, $result);
    }

    /** @return iterable<string, array{float, float|null, float, int, FeasibilityLevel}> */
    public static function feasibilityProvider(): iterable
    {
        yield 'faible' => [9, 32, 50, 8, FeasibilityLevel::LOW];
        yield 'moyen' => [11, 40, 42.2, 12, FeasibilityLevel::MEDIUM];
        yield 'bon' => [14, 48, 21.1, 12, FeasibilityLevel::GOOD];
        yield 'optimal' => [17, 58, 10, 16, FeasibilityLevel::OPTIMAL];
    }

    public function testTimeGoalIsAlsoSupported(): void
    {
        $plan = (new TrainingPlan())
            ->setTargetType('time')
            ->setTargetValue(90)
            ->setTargetUnit('min')
            ->setDurationWeeks(12);

        self::assertSame(
            FeasibilityLevel::GOOD,
            $this->service->evaluate($this->profile(14, 48), $plan, 8)
        );
    }

    public function testRaceGoalUsesBothDistanceAndTargetPace(): void
    {
        $plan = (new TrainingPlan())
            ->setTargetType('race')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTargetDurationMinutes(40)
            ->setDurationWeeks(8);

        self::assertSame(
            FeasibilityLevel::MEDIUM,
            $this->service->evaluate($this->profile(17, 58), $plan, 8)
        );
    }

    private function profile(float $vma, ?float $vo2max): Profile
    {
        return (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma($vma)
            ->setVo2max($vo2max);
    }

    private function distancePlan(float $distanceKm, int $durationWeeks): TrainingPlan
    {
        return (new TrainingPlan())
            ->setTargetType('distance')
            ->setTargetValue($distanceKm)
            ->setTargetUnit('km')
            ->setDurationWeeks($durationWeeks);
    }
}
