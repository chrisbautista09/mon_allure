<?php

namespace App\Tests\Unit\Service;

use App\Entity\TrainingPlan;
use App\Service\ProgressService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProgressServiceTest extends TestCase
{
    #[DataProvider('planDurationProvider')]
    public function testCalculatesProgressForEveryPlanDuration(
        int $currentWeek,
        int $durationWeeks,
        int $expectedPercentage,
    ): void {
        $plan = (new TrainingPlan())
            ->setCurrentWeek($currentWeek)
            ->setDurationWeeks($durationWeeks);

        self::assertSame(
            $expectedPercentage,
            $this->service()->calculatePlanProgress($plan),
        );
    }

    /** @return iterable<string, array{int, int, int}> */
    public static function planDurationProvider(): iterable
    {
        yield 'plan découverte de 8 semaines' => [3, 8, 38];
        yield 'plan intermédiaire de 12 semaines' => [4, 12, 33];
        yield 'plan performance de 18 semaines' => [9, 18, 50];
        yield 'durée nulle' => [1, 0, 0];
        yield 'semaine avant le début' => [-1, 12, 0];
        yield 'plan dépassé' => [20, 18, 100];
    }

    public function testReturnsZeroWhenPlanDurationIsMissing(): void
    {
        self::assertSame(
            0,
            $this->service()->calculatePlanProgress(new TrainingPlan()),
        );
    }

    #[DataProvider('phaseProvider')]
    public function testReturnsCurrentTrainingPhase(
        int $currentWeek,
        int $durationWeeks,
        string $expectedPhase,
    ): void {
        $plan = (new TrainingPlan())
            ->setCurrentWeek($currentWeek)
            ->setDurationWeeks($durationWeeks);

        self::assertSame($expectedPhase, $this->service()->getCurrentPhase($plan));
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function phaseProvider(): iterable
    {
        yield 'mise en condition' => [2, 12, 'Mise en condition'];
        yield 'développement endurance' => [4, 12, 'Développement endurance'];
        yield 'développement spécifique' => [9, 12, 'Développement spécifique'];
        yield 'affûtage' => [12, 12, 'Affûtage et objectif'];
    }

    #[DataProvider('currentWeekProvider')]
    public function testCalculatesCurrentWeekFromRealDates(
        string $today,
        int $expectedWeek,
    ): void {
        $plan = (new TrainingPlan())
            ->setStartDate(new \DateTimeImmutable('2026-07-01'))
            ->setEndDate(new \DateTimeImmutable('2026-09-22'))
            ->setDurationWeeks(12);

        self::assertSame($expectedWeek, $this->service($today)->calculateCurrentWeek($plan));
    }

    /** @return iterable<string, array{string, int}> */
    public static function currentWeekProvider(): iterable
    {
        yield 'avant le début' => ['2026-06-25', 1];
        yield 'premier jour' => ['2026-07-01', 1];
        yield 'fin de première semaine' => ['2026-07-07', 1];
        yield 'début de deuxième semaine' => ['2026-07-08', 2];
        yield 'troisième semaine' => ['2026-07-15', 3];
        yield 'après la fin avec plafonnement' => ['2027-01-01', 12];
    }

    public function testSynchronizesCurrentWeekOnlyWhenNeeded(): void
    {
        $plan = (new TrainingPlan())
            ->setStartDate(new \DateTimeImmutable('2026-07-01'))
            ->setEndDate(new \DateTimeImmutable('2026-09-22'))
            ->setDurationWeeks(12)
            ->setCurrentWeek(1);
        $service = $this->service('2026-07-15');

        self::assertTrue($service->synchronizeCurrentWeek($plan));
        self::assertSame(3, $plan->getCurrentWeek());
        self::assertFalse($service->synchronizeCurrentWeek($plan));
    }

    public function testIdentifiesCompletedPlanAfterItsEndDate(): void
    {
        $plan = (new TrainingPlan())
            ->setEndDate(new \DateTimeImmutable('2026-09-22'));

        self::assertFalse($this->service('2026-09-22')->isPlanCompleted($plan));
        self::assertTrue($this->service('2026-09-23')->isPlanCompleted($plan));
    }

    #[DataProvider('sportsProgressProvider')]
    public function testReturnsSafeSportsProgress(float $score, int $expected): void
    {
        $plan = (new TrainingPlan())->setProgressScore($score);

        self::assertSame($expected, $this->service()->getSportsProgress($plan));
    }

    /** @return iterable<string, array{float, int}> */
    public static function sportsProgressProvider(): iterable
    {
        yield 'score calculé' => [78.4, 78];
        yield 'arrondi supérieur' => [78.6, 79];
        yield 'score négatif protégé' => [-5.0, 0];
        yield 'score supérieur protégé' => [105.0, 100];
    }

    private function service(string $now = '2026-08-25'): ProgressService
    {
        return new ProgressService(new MockClock($now));
    }
}
