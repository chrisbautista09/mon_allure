<?php

namespace App\Tests\Unit\Service;

use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Repository\SessionIntensityZoneRepository;
use App\Service\ZoneDistributionService;
use PHPUnit\Framework\TestCase;

final class ZoneDistributionServiceTest extends TestCase
{
    public function testCalculatesReusableUserDistribution(): void
    {
        $user = new User();
        $repository = $this->createMock(SessionIntensityZoneRepository::class);
        $repository->expects(self::once())
            ->method('findDistributionByUser')
            ->with($user)
            ->willReturn([
                $this->row(1, 'Z1', 18, 1200),
                $this->row(2, 'Z2', 7, 500),
                $this->row(3, 'Z3', 5, 300),
            ]);

        $distribution = (new ZoneDistributionService($repository))->calculateDistribution($user);

        self::assertSame([
            ['zone' => 'Z1', 'sessions' => 18, 'percentage' => 60.0],
            ['zone' => 'Z2', 'sessions' => 7, 'percentage' => 23.33],
            ['zone' => 'Z3', 'sessions' => 5, 'percentage' => 16.67],
        ], $distribution);
        self::assertSame(100.0, array_sum(array_column($distribution, 'percentage')));
    }

    public function testRoundingAlwaysProducesExactlyOneHundredPercent(): void
    {
        $plan = new TrainingPlan();
        $repository = $this->createMock(SessionIntensityZoneRepository::class);
        $repository->expects(self::once())
            ->method('findDistributionByTrainingPlan')
            ->with($plan)
            ->willReturn([
                $this->row(1, 'Z1', 1, 100),
                $this->row(2, 'Z2', 1, 100),
                $this->row(3, 'Z3', 1, 100),
            ]);

        $distribution = (new ZoneDistributionService($repository))->calculatePlanDistribution($plan);

        self::assertSame([33.33, 33.33, 33.34], array_column($distribution, 'percentage'));
        self::assertSame(100.0, array_sum(array_column($distribution, 'percentage')));
    }

    public function testEmptyRepositoryResultProducesEmptyDistribution(): void
    {
        $repository = $this->createStub(SessionIntensityZoneRepository::class);
        $repository->method('findDistributionByUser')->willReturn([]);

        self::assertSame(
            [],
            (new ZoneDistributionService($repository))->calculateDistribution(new User()),
        );
    }

    public function testSingleValidZoneProducesOneHundredPercentAndIncompleteRowsAreIgnored(): void
    {
        $repository = $this->createStub(SessionIntensityZoneRepository::class);
        $repository->method('findDistributionByUser')->willReturn([
            $this->row(2, 'Z2', 4, 400),
            $this->row(99, 'UNKNOWN', 2, 200),
            $this->row(3, 'Z3', 0, 0),
        ]);

        self::assertSame([
            ['zone' => 'Z2', 'sessions' => 4, 'percentage' => 100.0],
        ], (new ZoneDistributionService($repository))->calculateDistribution(new User()));
    }

    public function testStatisticsExposeRealSessionCountAndDominantZone(): void
    {
        $user = new User();
        $repository = $this->createMock(SessionIntensityZoneRepository::class);
        $repository->expects(self::once())
            ->method('findDistributionByUser')
            ->with($user)
            ->willReturn([
                $this->row(1, 'Z1', 3, 200),
                $this->row(2, 'Z2', 5, 300),
            ]);
        $repository->expects(self::once())
            ->method('countDistinctSessionsByUser')
            ->with($user)
            ->willReturn(6);

        self::assertSame([
            'totalSessions' => 6,
            'dominantZone' => 'Z2',
            'zones' => [
                ['zone' => 'Z1', 'sessions' => 3, 'percentage' => 37.5],
                ['zone' => 'Z2', 'sessions' => 5, 'percentage' => 62.5],
            ],
        ], (new ZoneDistributionService($repository))->getStatistics($user));
    }

    /**
     * @return array{
     *     zoneId: int,
     *     name: string,
     *     occurrenceCount: int,
     *     durationPercentTotal: float
     * }
     */
    private function row(
        int $zoneId,
        string $name,
        int $occurrenceCount,
        float $durationPercentTotal,
    ): array {
        return compact('zoneId', 'name', 'occurrenceCount', 'durationPercentTotal');
    }
}
