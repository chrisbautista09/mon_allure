<?php

namespace App\Tests\Unit\Entity;

use App\Entity\TrainingPlan;
use PHPUnit\Framework\TestCase;

final class TrainingPlanMonitoringHistoryTest extends TestCase
{
    public function testMonitoringHistoryTracksDetectionResolutionAndRecurrenceWithoutDuplicates(): void
    {
        $plan = new TrainingPlan();
        $firstAnalysis = new \DateTimeImmutable('2026-08-27 08:00:00');
        $sameAnomalies = [
            'LOW_PROGRESS' => 'Progression insuffisante.',
            'LOW_SUCCESS_RATE' => 'Réussite insuffisante.',
        ];

        self::assertTrue($plan->synchronizeMonitoringHistory($sameAnomalies, $firstAnalysis));
        self::assertFalse($plan->synchronizeMonitoringHistory(
            $sameAnomalies,
            new \DateTimeImmutable('2026-08-27 09:00:00'),
        ));
        self::assertCount(2, $plan->getMonitoringHistory());
        self::assertSame($firstAnalysis->format(DATE_ATOM), $plan->getMonitoringHistory()[0]['createdAt']);

        $resolutionDate = new \DateTimeImmutable('2026-08-28 08:00:00');
        self::assertTrue($plan->synchronizeMonitoringHistory([
            'LOW_PROGRESS' => 'Progression insuffisante.',
        ], $resolutionDate));
        $resolvedEntry = $plan->getMonitoringHistory()[1];
        self::assertTrue($resolvedEntry['resolved']);
        self::assertSame($resolutionDate->format(DATE_ATOM), $resolvedEntry['resolvedAt']);

        $recurrenceDate = new \DateTimeImmutable('2026-08-29 08:00:00');
        self::assertTrue($plan->synchronizeMonitoringHistory($sameAnomalies, $recurrenceDate));
        $history = $plan->getMonitoringHistory();
        self::assertCount(3, $history);
        self::assertSame('LOW_SUCCESS_RATE', $history[2]['typeAnomaly']);
        self::assertFalse($history[2]['resolved']);
        self::assertSame($recurrenceDate->format(DATE_ATOM), $history[2]['createdAt']);
    }
}
