<?php

namespace App\Tests\Unit\Service;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\PerformanceRepository;
use App\Service\PerformanceStatisticsService;
use PHPUnit\Framework\TestCase;

final class PerformanceStatisticsServiceTest extends TestCase
{
    public function testPreparesChronologicalEvolutionSeriesForCharts(): void
    {
        $performances = [
            $this->performance('2026-08-01', 8, 3600, null),
            $this->performance('2026-08-08', 10, 3300, 120),
            $this->performance('2026-08-15', 12, 3000, 180),
        ];
        $service = $this->serviceReturning($performances);
        $user = new User();

        self::assertSame([
            ['date' => '2026-08-01', 'value' => 8.0],
            ['date' => '2026-08-08', 'value' => 10.0],
            ['date' => '2026-08-15', 'value' => 12.0],
        ], $service->getDistanceEvolution($user));
        self::assertSame([
            ['date' => '2026-08-01', 'value' => 3600],
            ['date' => '2026-08-08', 'value' => 3300],
            ['date' => '2026-08-15', 'value' => 3000],
        ], $service->getTimeEvolution($user));
        self::assertSame([
            ['date' => '2026-08-08', 'value' => 120],
            ['date' => '2026-08-15', 'value' => 180],
        ], $service->getElevationEvolution($user));
        $history = $service->getHistory($user);
        self::assertSame([8.0, 10.0, 12.0], array_column($history, 'distance'));
        self::assertSame([10.0, 10.0, 10.0], array_column(array_column($history, 'planned'), 'distance'));
        self::assertSame(
            ['not_attained', 'attained', 'attained'],
            array_map(static fn (array $item): string => $item['comparison']['distance']['status'], $history),
        );
        self::assertSame(
            ['attained', 'attained', 'close'],
            array_map(static fn (array $item): string => $item['comparison']['time']['status'], $history),
        );
        self::assertSame(
            ['unavailable', 'not_attained', 'attained'],
            array_map(static fn (array $item): string => $item['comparison']['elevation']['status'], $history),
        );
    }

    public function testCalculatesReliableGlobalStatisticsAndSportsProgression(): void
    {
        $service = $this->serviceReturning([
            $this->performance('2026-08-01', 8, 3600, 100),
            $this->performance('2026-08-08', 10, 3300, 150),
            $this->performance('2026-08-15', 12, 3000, 200),
        ]);

        self::assertSame([
            'performanceCount' => 3,
            'distance' => ['average' => 10.0, 'best' => 12.0, 'worst' => 8.0, 'progression' => 50.0],
            'time' => ['average' => 3300.0, 'best' => 3000, 'worst' => 3600, 'progression' => 16.67],
            'elevation' => ['average' => 150.0, 'best' => 200, 'worst' => 100, 'progression' => 100.0],
        ], $service->getGlobalStatistics(new User()));
        self::assertSame([
            'totalDistance' => 30.0,
            'totalTimeSec' => 9900,
            'totalElevation' => 450,
            'sessionCount' => 3,
            'averageDistance' => 10.0,
            'averageTimeSec' => 3300,
        ], $service->getSummaryStatistics(new User()));
    }

    public function testReturnsExplicitEmptyStatistics(): void
    {
        $statistics = $this->serviceReturning([])->getGlobalStatistics(new User());
        $emptyMetric = ['average' => null, 'best' => null, 'worst' => null, 'progression' => null];

        self::assertSame(0, $statistics['performanceCount']);
        self::assertSame($emptyMetric, $statistics['distance']);
        self::assertSame($emptyMetric, $statistics['time']);
        self::assertSame($emptyMetric, $statistics['elevation']);
        self::assertSame([
            'totalDistance' => 0.0,
            'totalTimeSec' => 0,
            'totalElevation' => 0,
            'sessionCount' => 0,
            'averageDistance' => 0.0,
            'averageTimeSec' => 0,
        ], $this->serviceReturning([])->getSummaryStatistics(new User()));
    }

    public function testComparisonThresholdsDistinguishCloseAndNotAttainedGoals(): void
    {
        $history = $this->serviceReturning([
            $this->performance('2026-08-20', 9, 2700, 140),
        ])->getHistory(new User());

        self::assertSame('close', $history[0]['comparison']['distance']['status']);
        self::assertSame(90.0, $history[0]['comparison']['distance']['percentage']);
        self::assertSame('not_attained', $history[0]['comparison']['time']['status']);
        self::assertSame(-900, $history[0]['comparison']['time']['difference']);
        self::assertSame('close', $history[0]['comparison']['elevation']['status']);
    }

    public function testUsesPeriodRepositoryAndRequiresBothBounds(): void
    {
        $user = new User();
        $start = new \DateTimeImmutable('2026-08-01');
        $end = new \DateTimeImmutable('2026-08-31');
        $repository = $this->createMock(PerformanceRepository::class);
        $repository->expects(self::once())
            ->method('findByPeriod')
            ->with($user, $start, $end)
            ->willReturn([$this->performance('2026-08-10', 10, 3600, 100)]);
        $service = new PerformanceStatisticsService($repository);

        self::assertCount(1, $service->getDistanceEvolution($user, $start, $end));

        $this->expectException(\InvalidArgumentException::class);
        $service->getDistanceEvolution($user, $start);
    }

    public function testCalculationsRemainExactWithThreeHundredPerformances(): void
    {
        $performances = [];

        for ($index = 1; $index <= 300; ++$index) {
            $performances[] = $this->performance(
                (new \DateTimeImmutable('2025-11-01'))->modify(sprintf('+%d days', $index - 1))->format('Y-m-d'),
                $index,
                3600,
                $index - 1,
            );
        }

        $service = $this->serviceReturning($performances);
        $history = $service->getHistory(new User());
        $summary = $service->getSummaryStatistics(new User());

        self::assertCount(300, $history);
        self::assertSame(1.0, $history[0]['distance']);
        self::assertSame(300.0, $history[299]['distance']);
        self::assertSame(45150.0, $summary['totalDistance']);
        self::assertSame(1080000, $summary['totalTimeSec']);
        self::assertSame(44850, $summary['totalElevation']);
        self::assertSame(300, $summary['sessionCount']);
        self::assertSame(150.5, $summary['averageDistance']);
        self::assertSame(3600, $summary['averageTimeSec']);
    }

    /** @param list<Performance> $performances */
    private function serviceReturning(array $performances): PerformanceStatisticsService
    {
        $repository = $this->createStub(PerformanceRepository::class);
        $repository->method('findByUser')->willReturn($performances);

        return new PerformanceStatisticsService($repository);
    }

    private function performance(
        string $date,
        float $distance,
        int $duration,
        ?int $elevation,
    ): Performance {
        $session = (new Session())
            ->setDate(new \DateTimeImmutable($date))
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setPlannedElevationDPlus(150);

        return (new Performance())
            ->setSession($session)
            ->setDistanceKm($distance)
            ->setDurationSec($duration)
            ->setElevationDPlus($elevation);
    }
}
