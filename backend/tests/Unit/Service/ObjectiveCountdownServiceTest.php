<?php

namespace App\Tests\Unit\Service;

use App\Entity\TrainingPlan;
use App\Service\ObjectiveCountdownService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ObjectiveCountdownServiceTest extends TestCase
{
    #[DataProvider('countdownProvider')]
    public function testCalculatesRemainingDaysWeeksAndStatus(
        string $today,
        string $endDate,
        int $expectedDays,
        int $expectedWeeks,
        string $expectedStatus,
    ): void {
        $plan = (new TrainingPlan())
            ->setEndDate(new \DateTimeImmutable($endDate));

        self::assertSame([
            'days_remaining' => $expectedDays,
            'weeks_remaining' => $expectedWeeks,
            'status' => $expectedStatus,
        ], $this->service($today)->calculateRemainingTime($plan));
    }

    /** @return iterable<string, array{string, string, int, int, string}> */
    public static function countdownProvider(): iterable
    {
        yield 'cas normal demandé du premier juillet au premier septembre' => [
            '2026-07-01',
            '2026-09-01',
            62,
            8,
            ObjectiveCountdownService::STATUS_UPCOMING,
        ];
        yield 'objectif proche dans trois jours' => [
            '2026-09-12',
            '2026-09-15',
            3,
            0,
            ObjectiveCountdownService::STATUS_UPCOMING,
        ];
        yield 'quarante-cinq jours restants' => [
            '2026-08-01',
            '2026-09-15',
            45,
            6,
            ObjectiveCountdownService::STATUS_UPCOMING,
        ];
        yield 'moins d’une semaine restante' => [
            '2026-09-10',
            '2026-09-15',
            5,
            0,
            ObjectiveCountdownService::STATUS_UPCOMING,
        ];
        yield 'jour de l’objectif' => [
            '2026-09-15',
            '2026-09-15',
            0,
            0,
            ObjectiveCountdownService::STATUS_REACHED,
        ];
        yield 'objectif dépassé' => [
            '2026-09-16',
            '2026-09-15',
            0,
            0,
            ObjectiveCountdownService::STATUS_COMPLETED,
        ];
    }

    public function testHandlesPlanWithoutObjectiveDate(): void
    {
        $plan = (new TrainingPlan())->setIsActive(false);

        self::assertSame([
            'days_remaining' => null,
            'weeks_remaining' => null,
            'status' => ObjectiveCountdownService::STATUS_NOT_DEFINED,
        ], $this->service('2026-08-01')->calculateRemainingTime($plan));
    }

    public function testTimeOfDayDoesNotChangeCalendarCountdown(): void
    {
        $plan = (new TrainingPlan())
            ->setEndDate(new \DateTimeImmutable('2026-08-02 00:00:00'));

        $result = $this->service('2026-08-01 23:59:59')->calculateRemainingTime($plan);

        self::assertSame(1, $result['days_remaining']);
    }

    #[DataProvider('timelineProvider')]
    public function testCalculatesCoherentElapsedAndRemainingTime(
        string $today,
        int $expectedElapsed,
        int $expectedRemaining,
        int $expectedPercentage,
    ): void {
        $plan = (new TrainingPlan())
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-09-19'));

        self::assertSame([
            'days_elapsed' => $expectedElapsed,
            'days_remaining' => $expectedRemaining,
            'total_days' => 49,
            'elapsed_percentage' => $expectedPercentage,
        ], $this->service($today)->calculateTimelineComparison($plan));
    }

    /** @return iterable<string, array{string, int, int, int}> */
    public static function timelineProvider(): iterable
    {
        yield 'avant le début' => ['2026-07-20', 0, 49, 0];
        yield 'au début' => ['2026-08-01', 0, 49, 0];
        yield 'en cours' => ['2026-08-22', 21, 28, 43];
        yield 'à la fin' => ['2026-09-19', 49, 0, 100];
        yield 'après la fin' => ['2026-10-01', 49, 0, 100];
    }

    public function testTimelineHandlesMissingDates(): void
    {
        self::assertSame([
            'days_elapsed' => null,
            'days_remaining' => null,
            'total_days' => null,
            'elapsed_percentage' => null,
        ], $this->service('2026-08-01')->calculateTimelineComparison(new TrainingPlan()));
    }

    private function service(string $now): ObjectiveCountdownService
    {
        return new ObjectiveCountdownService(new MockClock($now));
    }
}
