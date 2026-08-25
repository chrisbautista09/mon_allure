<?php

namespace App\Tests\Unit\Entity;

use App\Entity\TrainingPlan;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrainingPlanProgressTest extends TestCase
{
    #[DataProvider('progressProvider')]
    public function testCalculatesBoundedProgressPercentage(
        int $currentWeek,
        int $durationWeeks,
        int $expectedPercentage,
    ): void {
        $plan = (new TrainingPlan())
            ->setCurrentWeek($currentWeek)
            ->setDurationWeeks($durationWeeks);

        self::assertSame($expectedPercentage, $plan->getProgressPercentage());
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function progressProvider(): iterable
    {
        yield 'début du plan' => [1, 12, 8];
        yield 'quatrième semaine sur douze' => [4, 12, 33];
        yield 'moitié du plan' => [6, 12, 50];
        yield 'plan terminé' => [12, 12, 100];
        yield 'progression supérieure à la durée' => [14, 12, 100];
        yield 'semaine négative' => [-1, 12, 0];
        yield 'durée invalide' => [1, 0, 0];
    }

    public function testReturnsZeroWhenDurationIsNotInitialized(): void
    {
        self::assertSame(0, (new TrainingPlan())->getProgressPercentage());
    }

    public function testRequiredMonitoringFieldsExposeTheirDefaults(): void
    {
        $plan = new TrainingPlan();

        self::assertSame(1, $plan->getCurrentWeek());
        self::assertNull($plan->getDurationWeeks());
        self::assertTrue($plan->isActive());
        self::assertSame(0.0, $plan->getProgressScore());
    }
}
