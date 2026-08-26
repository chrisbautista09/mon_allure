<?php

namespace App\Tests\Unit\Service;

use App\Entity\TrainingPlan;
use App\Repository\SessionIntensityZoneRepository;
use App\Service\TrainingBalanceService;
use App\Service\ZoneDistributionService;
use PHPUnit\Framework\TestCase;

final class TrainingBalanceServiceTest extends TestCase
{
    public function testIntermediateReferenceDistributionIsBalanced(): void
    {
        $result = $this->analyze('intermediate', [
            $this->row('Z2', 2),
            $this->row('Z4', 1),
        ], 3);

        self::assertSame('balanced', $result['status']);
        self::assertSame('La répartition de votre plan est équilibrée.', $result['message']);
        self::assertSame(['endurance' => 66.67, 'threshold' => 33.33, 'vma' => 0.0], $result['actual']);
    }

    public function testPerformancePlanWithExcessiveIntensityIsFlagged(): void
    {
        $result = $this->analyze('performance', [
            $this->row('Z2', 1),
            $this->row('Z4', 2),
            $this->row('Z5', 2),
        ], 5);

        self::assertSame('too_intensive', $result['status']);
        self::assertSame('Trop de séances intensives dans ce plan.', $result['message']);
    }

    public function testMissingThresholdWorkIsFlagged(): void
    {
        $result = $this->analyze('intermediate', [
            $this->row('Z2', 9),
            $this->row('Z4', 1),
        ], 10);

        self::assertSame('threshold_deficit', $result['status']);
        self::assertSame('Le plan manque de travail au seuil.', $result['message']);
    }

    public function testPlanWithoutZoneDataCannotBeAnalyzed(): void
    {
        $result = $this->analyze('discovery', [], 0);

        self::assertSame('unavailable', $result['status']);
        self::assertStringContainsString('Ajoutez des séances', $result['message']);
    }

    /**
     * @param list<array{zoneId: int, name: string, occurrenceCount: int, durationPercentTotal: float}> $rows
     *
     * @return array<string, mixed>
     */
    private function analyze(string $pole, array $rows, int $sessionCount): array
    {
        $plan = (new TrainingPlan())->setPoleType($pole);
        $repository = $this->createMock(SessionIntensityZoneRepository::class);
        $repository->expects(self::once())
            ->method('findDistributionByTrainingPlan')
            ->with($plan)
            ->willReturn($rows);
        $repository->expects(self::once())
            ->method('countDistinctSessionsByTrainingPlan')
            ->with($plan)
            ->willReturn($sessionCount);

        return (new TrainingBalanceService(new ZoneDistributionService($repository)))->analyze($plan);
    }

    /** @return array{zoneId: int, name: string, occurrenceCount: int, durationPercentTotal: float} */
    private function row(string $zone, int $occurrences): array
    {
        return [
            'zoneId' => (int) substr($zone, 1),
            'name' => $zone,
            'occurrenceCount' => $occurrences,
            'durationPercentTotal' => $occurrences * 100.0,
        ];
    }
}
