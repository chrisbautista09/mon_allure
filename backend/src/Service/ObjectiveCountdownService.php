<?php

namespace App\Service;

use App\Entity\TrainingPlan;
use Symfony\Component\Clock\ClockInterface;

final class ObjectiveCountdownService
{
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_REACHED = 'reached';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NOT_DEFINED = 'not_defined';

    public function __construct(private readonly ClockInterface $clock)
    {
    }

    /**
     * @return array{
     *     days_remaining: int|null,
     *     weeks_remaining: int|null,
     *     status: string
     * }
     */
    public function calculateRemainingTime(TrainingPlan $plan): array
    {
        $endDate = $plan->getEndDate();

        if ($endDate === null) {
            return [
                'days_remaining' => null,
                'weeks_remaining' => null,
                'status' => self::STATUS_NOT_DEFINED,
            ];
        }

        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
        $objectiveDate = $endDate->setTime(0, 0);

        if ($objectiveDate < $today) {
            return [
                'days_remaining' => 0,
                'weeks_remaining' => 0,
                'status' => self::STATUS_COMPLETED,
            ];
        }

        $daysRemaining = (int) $today->diff($objectiveDate)->days;

        return [
            'days_remaining' => $daysRemaining,
            'weeks_remaining' => intdiv($daysRemaining, 7),
            'status' => $daysRemaining === 0
                ? self::STATUS_REACHED
                : self::STATUS_UPCOMING,
        ];
    }

    /**
     * @return array{
     *     days_elapsed: int|null,
     *     days_remaining: int|null,
     *     total_days: int|null,
     *     elapsed_percentage: int|null
     * }
     */
    public function calculateTimelineComparison(TrainingPlan $plan): array
    {
        $startDate = $plan->getStartDate();
        $endDate = $plan->getEndDate();

        if ($startDate === null || $endDate === null || $endDate < $startDate) {
            return [
                'days_elapsed' => null,
                'days_remaining' => null,
                'total_days' => null,
                'elapsed_percentage' => null,
            ];
        }

        $today = \DateTimeImmutable::createFromInterface($this->clock->now())->setTime(0, 0);
        $startDate = $startDate->setTime(0, 0);
        $endDate = $endDate->setTime(0, 0);
        $totalDays = (int) $startDate->diff($endDate)->days;
        $daysElapsed = match (true) {
            $today <= $startDate => 0,
            $today >= $endDate => $totalDays,
            default => (int) $startDate->diff($today)->days,
        };
        $daysRemaining = $totalDays - $daysElapsed;

        return [
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'total_days' => $totalDays,
            'elapsed_percentage' => $totalDays === 0
                ? 100
                : (int) round(($daysElapsed / $totalDays) * 100),
        ];
    }
}
