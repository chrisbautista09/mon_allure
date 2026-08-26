<?php

namespace App\Tests\Unit\Service;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Enum\FormStatusLevel;
use App\Enum\FormStatusDataState;
use App\Enum\FormTrend;
use App\Repository\PerformanceRepository;
use App\Service\FormStatusService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class FormStatusServiceTest extends TestCase
{
    public function testReturnsNoActivePlanState(): void
    {
        $user = new User();
        $result = $this->service($user)->calculate($user);

        self::assertNull($result->score);
        self::assertNull($result->status);
        self::assertSame(FormTrend::UNKNOWN, $result->trend);
        self::assertSame(FormStatusDataState::NO_ACTIVE_PLAN, $result->dataState);
        self::assertSame(0, $result->recentPerformanceCount);
        self::assertNull($result->toArray()['status']);
        self::assertSame('NO_ACTIVE_PLAN', $result->toArray()['dataState']);
        self::assertSame(
            'Effectuez davantage de séances pour afficher une tendance.',
            $result->toArray()['trendMessage'],
        );
    }

    public function testNewUserWithPlanReceivesNoPerformanceState(): void
    {
        [$user] = $this->context(75);

        $result = $this->service($user)->calculate($user);

        self::assertNull($result->score);
        self::assertNull($result->status);
        self::assertSame(FormTrend::UNKNOWN, $result->trend);
        self::assertSame(FormStatusDataState::NO_PERFORMANCE, $result->dataState);
        self::assertSame(
            'Réalisez vos premières séances pour calculer votre état de forme.',
            $result->dataState->message(),
        );
        self::assertSame(0, $result->recentPerformanceCount);
    }

    public function testCombinesPlanAndRecentPerformanceScores(): void
    {
        [$user, $plan] = $this->context(80);
        $this->addPerformance($user, $plan, 100, '2026-08-25 08:00:00');

        $result = $this->service($user)->calculate($user);

        self::assertSame(94, $result->score);
        self::assertSame(FormStatusLevel::EXCELLENT, $result->status);
        self::assertSame(FormStatusDataState::LIMITED_DATA, $result->dataState);
        self::assertSame(1, $result->recentPerformanceCount);
        self::assertSame('2026-08-26T09:00:00+02:00', $result->calculatedAt->format(DATE_ATOM));
    }

    public function testIgnoresOldIncompleteAndOtherPlanPerformances(): void
    {
        [$user, $activePlan] = $this->context(50);
        $otherPlan = $this->plan(100, false, '2026-07-01');
        $user->addTrainingPlan($otherPlan);
        $this->addPerformance($user, $activePlan, 100, '2026-08-20 08:00:00');
        $this->addPerformance($user, $activePlan, 20, '2026-07-01 08:00:00');
        $this->addPerformance($user, $otherPlan, 20, '2026-08-24 08:00:00');
        $this->addIncompletePerformance($user, $activePlan, '2026-08-23 08:00:00');

        $result = $this->service($user)->calculate($user);

        self::assertSame(85, $result->score);
        self::assertSame(FormStatusLevel::GOOD, $result->status);
        self::assertSame(1, $result->recentPerformanceCount);
    }

    public function testDetectsImprovingTrendFromTwoPerformanceSamples(): void
    {
        [$user, $plan] = $this->context(70);

        foreach ([90, 90, 90, 70, 70, 70] as $index => $score) {
            $this->addPerformance(
                $user,
                $plan,
                $score,
                sprintf('2026-08-%02d 08:00:00', 25 - $index),
            );
        }

        $result = $this->service($user)->calculate($user);

        self::assertSame(80, $result->score);
        self::assertSame(FormTrend::IMPROVING, $result->trend);
        self::assertSame('progression', $result->trend->label());
        self::assertSame('Votre forme progresse.', $result->toArray()['trendMessage']);
        self::assertSame(6, $result->recentPerformanceCount);
        self::assertSame(FormStatusDataState::READY, $result->dataState);
    }

    public function testCompletedPlanReturnsItsLastReachedLevel(): void
    {
        $user = new User();
        $plan = $this->plan(88, true, '2026-06-01')
            ->setEndDate(new \DateTimeImmutable('2026-08-01'));
        $user->addTrainingPlan($plan);

        $result = $this->service($user)->calculate($user);

        self::assertSame(88, $result->score);
        self::assertSame(FormStatusLevel::GOOD, $result->status);
        self::assertSame(FormStatusDataState::PLAN_COMPLETED, $result->dataState);
        self::assertSame('Dernier niveau atteint à la fin de votre plan.', $result->dataState->message());
    }

    /** @param list<int> $scores */
    #[DataProvider('trendProvider')]
    public function testDetectsEveryTrendAtDefinedThresholds(
        array $scores,
        FormTrend $expectedTrend,
        string $expectedLabel,
        string $expectedMessage,
    ): void {
        [$user, $plan] = $this->context(70);

        foreach ($scores as $index => $score) {
            $this->addPerformance(
                $user,
                $plan,
                $score,
                sprintf('2026-08-%02d 08:00:00', 25 - $index),
            );
        }

        $result = $this->service($user)->calculate($user);

        self::assertSame($expectedTrend, $result->trend);
        self::assertSame($expectedLabel, $result->trend->label());
        self::assertSame($expectedMessage, $result->trend->message());
    }

    /** @return iterable<string, array{list<int>, FormTrend, string, string}> */
    public static function trendProvider(): iterable
    {
        yield 'progression au seuil de cinq points' => [
            [80, 80, 80, 75, 75, 75],
            FormTrend::IMPROVING,
            'progression',
            'Votre forme progresse.',
        ];
        yield 'baisse au seuil de cinq points' => [
            [70, 70, 70, 75, 75, 75],
            FormTrend::DECLINING,
            'baisse',
            'Votre charge semble élevée.',
        ];
        yield 'variation inférieure au seuil' => [
            [79, 79, 79, 75, 75, 75],
            FormTrend::STABLE,
            'stable',
            'Votre forme est stable.',
        ];
    }

    public function testRecentPerformanceHasMoreImpactThanAnOlderOne(): void
    {
        [$improvingUser, $improvingPlan] = $this->context(50);
        $this->addPerformance($improvingUser, $improvingPlan, 100, '2026-08-25 08:00:00');
        $this->addPerformance($improvingUser, $improvingPlan, 50, '2026-08-20 08:00:00');
        [$decliningUser, $decliningPlan] = $this->context(50);
        $this->addPerformance($decliningUser, $decliningPlan, 50, '2026-08-25 08:00:00');
        $this->addPerformance($decliningUser, $decliningPlan, 100, '2026-08-20 08:00:00');

        $improving = $this->service($improvingUser)->calculate($improvingUser);
        $declining = $this->service($decliningUser)->calculate($decliningUser);

        self::assertSame(73, $improving->score);
        self::assertSame(62, $declining->score);
        self::assertGreaterThan($declining->score, $improving->score);
    }

    public function testProgressScoreRaisesOrReducesFormAtEqualPerformance(): void
    {
        [$progressingUser, $progressingPlan] = $this->context(90);
        $this->addPerformance($progressingUser, $progressingPlan, 80, '2026-08-25 08:00:00');
        [$strugglingUser, $strugglingPlan] = $this->context(30);
        $this->addPerformance($strugglingUser, $strugglingPlan, 80, '2026-08-25 08:00:00');

        $progressing = $this->service($progressingUser)->calculate($progressingUser);
        $struggling = $this->service($strugglingUser)->calculate($strugglingUser);

        self::assertSame(83, $progressing->score);
        self::assertSame(65, $struggling->score);
        self::assertGreaterThan($struggling->score, $progressing->score);
    }

    public function testHighProgressAndRisingPerformancesProduceExcellentForm(): void
    {
        [$user, $plan] = $this->context(90);

        foreach ([95, 95, 95, 75, 75, 75] as $index => $score) {
            $this->addPerformance(
                $user,
                $plan,
                $score,
                sprintf('2026-08-%02d 08:00:00', 25 - $index),
            );
        }

        $result = $this->service($user)->calculate($user);

        self::assertSame(90, $result->score);
        self::assertSame(FormStatusLevel::EXCELLENT, $result->status);
        self::assertSame(FormTrend::IMPROVING, $result->trend);
        self::assertSame(FormStatusDataState::READY, $result->dataState);
    }

    public function testLowProgressAndRepeatedFailuresProduceReducedForm(): void
    {
        [$user, $plan] = $this->context(20);

        foreach ([30, 30, 30, 60, 60, 60] as $index => $score) {
            $this->addPerformance(
                $user,
                $plan,
                $score,
                sprintf('2026-08-%02d 08:00:00', 25 - $index),
            );
        }

        $result = $this->service($user)->calculate($user);

        self::assertSame(33, $result->score);
        self::assertSame(FormStatusLevel::LOW, $result->status);
        self::assertSame(FormTrend::DECLINING, $result->trend);
        self::assertSame('Votre charge semble élevée.', $result->trend->message());
        self::assertSame(FormStatusDataState::READY, $result->dataState);
    }

    #[DataProvider('levelProvider')]
    public function testMapsEveryScoreBoundary(float $score, FormStatusLevel $expected): void
    {
        [$user, $plan] = $this->context($score);
        $this->addPerformance($user, $plan, $score <= 0 ? 1 : min(100, $score), '2026-08-25 08:00:00');

        $result = $this->service($user)->calculate($user);

        self::assertSame($expected, $result->status);
    }

    /** @return iterable<string, array{float, FormStatusLevel}> */
    public static function levelProvider(): iterable
    {
        yield 'minimum' => [0, FormStatusLevel::LOW];
        yield 'fin forme faible' => [39, FormStatusLevel::LOW];
        yield 'début forme correcte' => [40, FormStatusLevel::FAIR];
        yield 'fin forme correcte' => [69, FormStatusLevel::FAIR];
        yield 'début bonne forme' => [70, FormStatusLevel::GOOD];
        yield 'fin bonne forme' => [89, FormStatusLevel::GOOD];
        yield 'début très bonne forme' => [90, FormStatusLevel::EXCELLENT];
        yield 'maximum' => [100, FormStatusLevel::EXCELLENT];
        yield 'score négatif borné' => [-10, FormStatusLevel::LOW];
        yield 'score supérieur borné' => [120, FormStatusLevel::EXCELLENT];
    }

    /** @return array{User, TrainingPlan} */
    private function context(float $progressScore): array
    {
        $user = new User();
        $plan = $this->plan($progressScore, true, '2026-08-01');
        $user->addTrainingPlan($plan);

        return [$user, $plan];
    }

    private function plan(float $progressScore, bool $active, string $startDate): TrainingPlan
    {
        return (new TrainingPlan())
            ->setProgressScore($progressScore)
            ->setIsActive($active)
            ->setStartDate(new \DateTimeImmutable($startDate));
    }

    private function addPerformance(
        User $user,
        TrainingPlan $plan,
        float $performanceScore,
        string $createdAt,
    ): void {
        $session = (new Session())
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60);
        $performance = (new Performance())
            ->setDistanceKm($performanceScore / 10)
            ->setDurationSec(3600)
            ->setCreatedAt(new \DateTimeImmutable($createdAt));
        $plan->addSession($session);
        $session->setPerformance($performance);
        $user->addPerformance($performance);
    }

    private function addIncompletePerformance(
        User $user,
        TrainingPlan $plan,
        string $createdAt,
    ): void {
        $session = new Session();
        $performance = (new Performance())
            ->setDistanceKm(10)
            ->setDurationSec(3600)
            ->setCreatedAt(new \DateTimeImmutable($createdAt));
        $plan->addSession($session);
        $session->setPerformance($performance);
        $user->addPerformance($performance);
    }

    private function service(User $user): FormStatusService
    {
        $repository = $this->createStub(PerformanceRepository::class);
        $repository
            ->method('findRecentPerformances')
            ->willReturn($user->getPerformances()->toArray());

        return new FormStatusService(
            new MockClock('2026-08-26 09:00:00 Europe/Paris'),
            $repository,
        );
    }
}
