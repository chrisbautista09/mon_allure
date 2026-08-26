<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use App\Entity\IntensityZone;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Service\ObjectiveCountdownService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TrainingPlanControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testAuthenticatedUserCanGenerateAndPersistCompletePlan(): void
    {
        $user = $this->userWithProfile();
        $this->persistAlgorithmData();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->jsonRequest('POST', '/api/training-plans', [
            'poleType' => 'intermediate',
            'targetType' => 'distance',
            'targetValue' => 10,
            'targetUnit' => 'km',
            'terrainType' => 'road',
            'elevationTargetDPlus' => 0,
        ]);

        self::assertResponseStatusCodeSame(201);
        $response = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('intermediate', $response['poleType']);
        self::assertSame(8, $response['durationWeeks']);
        self::assertSame(24, $response['sessionsCount']);
        self::assertSame(1, $this->entityManager->getRepository(TrainingPlan::class)->count([]));
        self::assertSame(24, $this->entityManager->getRepository(Session::class)->count([]));

        $plan = $this->entityManager->getRepository(TrainingPlan::class)->findOneBy([]);
        self::assertInstanceOf(TrainingPlan::class, $plan);
        self::assertSame($user->getId(), $plan->getUser()?->getId());
    }

    public function testAnonymousUserCannotGeneratePlan(): void
    {
        $this->client->jsonRequest('POST', '/api/training-plans', []);

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testOwnerCanRetrievePlanWithChronologicallyOrderedSessions(): void
    {
        $owner = $this->userWithProfile();
        $this->persistAlgorithmData();
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);
        $this->client->jsonRequest('POST', '/api/training-plans', $this->validGoal());
        self::assertResponseStatusCodeSame(201);
        $createdPlan = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->client->request('GET', sprintf('/api/training-plans/%d', $createdPlan['id']));

        self::assertResponseIsSuccessful();
        $response = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame($createdPlan['id'], $response['id']);
        self::assertCount(8, $response['weeks']);
        self::assertSame(range(1, 8), array_column($response['weeks'], 'week'));

        $dates = [];
        foreach ($response['weeks'] as $week) {
            self::assertCount(3, $week['sessions']);
            array_push($dates, ...array_column($week['sessions'], 'date'));
        }

        $sortedDates = $dates;
        sort($sortedDates);
        self::assertSame($sortedDates, $dates);

        $crawler = $this->client->request('GET', '/training/weekly');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#training-plan-title', 'Objectif 10 km');
        self::assertSelectorTextContains('#week-1', 'Semaine 1');
        self::assertSelectorTextContains('[data-testid="current-week"]', 'Semaine 1 / 8');
        self::assertSelectorTextContains('[data-testid="current-phase"]', 'Mise en condition');
        self::assertSelectorExists('[data-testid="plan-progress-bar"]');
        self::assertSelectorTextContains('[data-testid="plan-progress-percentage"]', '13 %');
        self::assertSelectorExists('[role="progressbar"][aria-valuenow="13"]');
        self::assertSelectorExists('[data-testid="plan-progress-fill"][style*="width: 13%"]');
        self::assertSelectorTextContains('[data-testid="sports-progress-percentage"]', '0 %');
        self::assertSelectorExists('[data-testid="sports-progress-bar"]');
        self::assertSelectorExists('[aria-label="Progression sportive"][aria-valuenow="0"]');
        self::assertSelectorExists('[data-testid="form-status-card"][data-form-status="UNAVAILABLE"]');
        self::assertSelectorTextContains('[data-testid="form-status-label"]', 'État de forme indisponible');
        self::assertSelectorTextContains('[data-testid="form-status-score"]', '— / 100');
        self::assertSelectorNotExists('[aria-label="Score de forme"]');
        self::assertSelectorTextContains('[data-testid="form-status-data-state"]', 'Réalisez vos premières séances');
        self::assertSelectorTextContains('[data-testid="form-status-trend"]', 'Données insuffisantes');
        self::assertSelectorExists('[data-testid="objective-countdown"][data-countdown-status="upcoming"]');
        self::assertSelectorExists(sprintf(
            '[data-testid="objective-date"][datetime="%s"]',
            $createdPlan['endDate'],
        ));
        $remainingDays = (int) (new \DateTimeImmutable('today'))->diff(
            new \DateTimeImmutable($createdPlan['endDate']),
        )->days;
        self::assertSelectorTextContains(
            '[data-testid="days-remaining"]',
            sprintf('%d jours', $remainingDays),
        );
        self::assertSelectorTextContains(
            '[data-testid="weeks-remaining"]',
            sprintf('%d semaines', intdiv($remainingDays, 7)),
        );
        self::assertSelectorTextContains('h3', 'Endurance fondamentale');
        self::assertSelectorTextContains('article .session-instructions', 'Cible');
        self::assertSelectorTextContains(
            '[data-testid="export-training-plan-pdf"]',
            'Exporter mon plan PDF',
        );
        self::assertSelectorExists(sprintf(
            'a[data-testid="export-training-plan-pdf"][href="/api/training-plans/%d/export"][download="plan-entrainement-%d.pdf"]',
            $createdPlan['id'],
            $createdPlan['id'],
        ));
        self::assertCount(24, $crawler->filter('article[data-session-date]'));

        $displayedDates = $crawler->filter('article[data-session-date]')->each(
            static fn ($node): string => (string) $node->attr('data-session-date'),
        );
        $sortedDisplayedDates = $displayedDates;
        sort($sortedDisplayedDates);
        self::assertSame($sortedDisplayedDates, $displayedDates);

        $otherUser = (new User())
            ->setEmail('other@example.com')
            ->setPseudo('other-runner')
            ->setPassword('test-password');
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->request('GET', sprintf('/api/training-plans/%d', $createdPlan['id']));

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnerCanExportPlanAsDownloadedPdf(): void
    {
        $owner = $this->userWithProfile();
        $this->persistAlgorithmData();
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);
        $this->client->jsonRequest('POST', '/api/training-plans', $this->validGoal());
        self::assertResponseStatusCodeSame(201);
        $created = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->client->request('GET', sprintf('/api/training-plans/%d/export', $created['id']));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame(
            'Content-Disposition',
            sprintf('attachment; filename=plan-entrainement-%d.pdf', $created['id']),
        );
        self::assertTrue(
            $this->client->getResponse()->headers->hasCacheControlDirective('no-store'),
        );
        self::assertTrue(
            $this->client->getResponse()->headers->hasCacheControlDirective('private'),
        );
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Content-Security-Policy', 'sandbox');
        $pdf = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10_000, strlen($pdf));
    }

    public function testOwnerCanRetrievePlanProgress(): void
    {
        $owner = $this->userWithProfile();
        $plan = $this->completePlanOwnedBy($owner)
            ->setCurrentWeek(1)
            ->setDurationWeeks(12)
            ->setStartDate(new \DateTimeImmutable('today -21 days'))
            ->setEndDate(new \DateTimeImmutable('today +63 days'));
        $plan->setProgressScore(78.4);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', sprintf('/api/training-plans/%d/progress', $plan->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'current_week' => 4,
            'total_weeks' => 12,
            'progress_percentage' => 33,
            'sports_progress_score' => 78,
            'is_completed' => false,
        ], json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
        self::assertSame(4, $plan->getCurrentWeek());
    }

    public function testOwnerCanRetrievePlanCountdown(): void
    {
        $owner = $this->userWithProfile();
        $objectiveDate = new \DateTimeImmutable('today +45 days');
        $plan = $this->completePlanOwnedBy($owner)
            ->setStartDate(new \DateTimeImmutable('today'))
            ->setEndDate($objectiveDate);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', sprintf('/api/training-plans/%d/countdown', $plan->getId()));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame([
            'objective_date' => $objectiveDate->format('Y-m-d'),
            'days_remaining' => 45,
            'weeks_remaining' => 6,
            'status' => ObjectiveCountdownService::STATUS_UPCOMING,
            'timeline' => [
                'days_elapsed' => 0,
                'days_remaining' => 45,
                'total_days' => 45,
                'elapsed_percentage' => 0,
            ],
        ], json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        ));
    }

    public function testCountdownEndpointReturnsZeroForPastObjective(): void
    {
        $owner = $this->userWithProfile();
        $plan = $this->completePlanOwnedBy($owner)
            ->setStartDate(new \DateTimeImmutable('today -84 days'))
            ->setEndDate(new \DateTimeImmutable('yesterday'));
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', sprintf('/api/training-plans/%d/countdown', $plan->getId()));

        self::assertResponseIsSuccessful();
        $response = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame(0, $response['days_remaining']);
        self::assertSame(0, $response['weeks_remaining']);
        self::assertSame(ObjectiveCountdownService::STATUS_COMPLETED, $response['status']);
    }

    #[DataProvider('countdownDisplayStateProvider')]
    public function testWeeklyPageDisplaysCountdownState(
        string $endDate,
        string $expectedStatus,
        string $expectedMessage,
    ): void {
        $owner = $this->userWithProfile();
        $this->completePlanOwnedBy($owner)
            ->setStartDate(new \DateTimeImmutable('today -7 days'))
            ->setEndDate(new \DateTimeImmutable($endDate));
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/training/weekly');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            '[data-testid="objective-countdown"][data-countdown-status="%s"]',
            $expectedStatus,
        ));
        self::assertSelectorTextContains('[data-testid="countdown-message"]', $expectedMessage);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function countdownDisplayStateProvider(): iterable
    {
        yield 'objectif futur' => [
            'today +45 days',
            ObjectiveCountdownService::STATUS_UPCOMING,
            'Encore 45 jours avant votre course',
        ];
        yield 'objectif atteint aujourd’hui' => [
            'today',
            ObjectiveCountdownService::STATUS_REACHED,
            'Objectif atteint 🎉',
        ];
        yield 'objectif dépassé' => [
            'yesterday',
            ObjectiveCountdownService::STATUS_COMPLETED,
            'Préparation terminée',
        ];
    }

    public function testUserCannotRetrieveAnotherUsersPlanCountdown(): void
    {
        $owner = $this->userWithProfile();
        $otherUser = (new User())
            ->setEmail('countdown-other@example.com')
            ->setPseudo('countdown-other-runner')
            ->setPassword('test-password');
        $plan = $this->completePlanOwnedBy($owner);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->request('GET', sprintf('/api/training-plans/%d/countdown', $plan->getId()));

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testWeeklyPageComparesPlanProgressWithAvailableTime(): void
    {
        $owner = $this->userWithProfile();
        $this->completePlanOwnedBy($owner)
            ->setStartDate(new \DateTimeImmutable('today -21 days'))
            ->setEndDate(new \DateTimeImmutable('today +28 days'))
            ->setDurationWeeks(12)
            ->setCurrentWeek(1);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/training/weekly');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '[data-testid="countdown-plan-progress"]',
            'Semaine 4 / 12 · 33 %',
        );
        self::assertSelectorTextContains('[data-testid="days-elapsed"]', '21 jours');
        self::assertSelectorTextContains('[data-testid="timeline-days-remaining"]', '28 jours');
        self::assertSelectorExists(
            '[aria-label="Temps écoulé dans la préparation"][aria-valuenow="43"]',
        );
        self::assertSelectorExists('[data-testid="timeline-progress-fill"][style*="width: 43%"]');
    }

    public function testAnonymousUserCannotRetrievePlanCountdown(): void
    {
        $this->client->request('GET', '/api/training-plans/1/countdown');

        self::assertResponseRedirects('http://localhost/login');
    }

    #[DataProvider('displayProgressProvider')]
    public function testWeeklyPageDisplaysProgressMatchingCurrentWeek(
        string $startDate,
        string $endDate,
        int $expectedWeek,
        int $expectedPercentage,
        bool $expectedCompleted,
    ): void {
        $owner = $this->userWithProfile();
        $plan = $this->completePlanOwnedBy($owner)
            ->setStartDate(new \DateTimeImmutable($startDate))
            ->setEndDate(new \DateTimeImmutable($endDate))
            ->setDurationWeeks(12)
            ->setCurrentWeek(1);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/training/weekly');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '[data-testid="current-week"]',
            sprintf('Semaine %d / 12', $expectedWeek),
        );
        self::assertSelectorTextContains(
            '[data-testid="plan-progress-percentage"]',
            sprintf('%d %%', $expectedPercentage),
        );
        self::assertSelectorExists(sprintf(
            '[role="progressbar"][aria-label="Progression calendrier"][aria-valuenow="%d"]',
            $expectedPercentage,
        ));
        self::assertSelectorExists(sprintf(
            '[data-testid="plan-progress-fill"][style*="width: %d%%"]',
            $expectedPercentage,
        ));

        if ($expectedCompleted) {
            self::assertSelectorTextContains('[data-testid="plan-completed"]', 'Plan terminé');
        } else {
            self::assertSelectorNotExists('[data-testid="plan-completed"]');
        }

        self::assertSame($expectedWeek, $plan->getCurrentWeek());
    }

    /** @return iterable<string, array{string, string, int, int, bool}> */
    public static function displayProgressProvider(): iterable
    {
        yield 'début semaine 1 sur 12' => [
            'today',
            'today +83 days',
            1,
            8,
            false,
        ];
        yield 'milieu semaine 6 sur 12' => [
            'today -35 days',
            'today +48 days',
            6,
            50,
            false,
        ];
        yield 'plan terminé semaine 12 sur 12' => [
            'today -84 days',
            'yesterday',
            12,
            100,
            true,
        ];
    }

    public function testUserCannotRetrieveAnotherUsersPlanProgress(): void
    {
        $owner = $this->userWithProfile();
        $otherUser = (new User())
            ->setEmail('progress-other@example.com')
            ->setPseudo('progress-other-runner')
            ->setPassword('test-password');
        $plan = $this->completePlanOwnedBy($owner);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->request('GET', sprintf('/api/training-plans/%d/progress', $plan->getId()));

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testAnonymousUserCannotRetrievePlanProgress(): void
    {
        $this->client->request('GET', '/api/training-plans/1/progress');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testCompleteTwelveWeekTenKilometrePlanIsExportedAsReadablePdf(): void
    {
        $owner = $this->userWithProfile();
        $this->persistAlgorithmData(12);
        $this->entityManager->persist($owner);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->jsonRequest('POST', '/api/training-plans', $this->validGoal());

        self::assertResponseStatusCodeSame(201);
        $created = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame(12, $created['durationWeeks']);
        self::assertSame(36, $created['sessionsCount']);
        $plan = $this->entityManager->getRepository(TrainingPlan::class)->find($created['id']);
        self::assertInstanceOf(TrainingPlan::class, $plan);
        self::assertSame(10.0, $plan->getTargetValue());
        self::assertSame(36, $this->entityManager->getRepository(Session::class)->count([
            'trainingPlan' => $plan,
        ]));

        $this->client->request('GET', sprintf('/api/training-plans/%d/export', $created['id']));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        $pdf = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertMatchesRegularExpression('/%%EOF\s*$/', $pdf);
        self::assertStringContainsString('xref', $pdf);
        self::assertGreaterThan(25_000, strlen($pdf));
        self::assertGreaterThanOrEqual(12, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    public function testUserCannotExportAnotherUsersPlan(): void
    {
        $owner = $this->userWithProfile();
        $otherUser = (new User())
            ->setEmail('pdf-other@example.com')
            ->setPseudo('pdf-other-runner')
            ->setPassword('test-password');
        $plan = $this->completePlanOwnedBy($owner);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->request('GET', sprintf('/api/training-plans/%d/export', $plan->getId()));

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testAnonymousUserCannotExportPlan(): void
    {
        $this->client->request('GET', '/api/training-plans/1/export');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testInvalidPayloadDoesNotPersistPartialPlan(): void
    {
        $user = $this->userWithProfile();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->jsonRequest('POST', '/api/training-plans', [
            'poleType' => 'unknown',
            'targetType' => 'distance',
            'targetValue' => 10,
            'targetUnit' => 'km',
            'terrainType' => 'road',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(TrainingPlan::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(Session::class)->count([]));
    }

    #[DataProvider('poleProvider')]
    public function testCompleteGenerationAndViewingChainForEveryPole(
        string $poleType,
        int $sessionsPerWeek,
    ): void {
        $user = $this->userWithProfile();
        $this->persistAlgorithmData();
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);
        $goal = $this->validGoal();
        $goal['poleType'] = $poleType;

        $this->client->jsonRequest('POST', '/api/training-plans', $goal);

        self::assertResponseStatusCodeSame(201);
        $created = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $expectedSessions = 8 * $sessionsPerWeek;
        self::assertSame($poleType, $created['poleType']);
        self::assertSame($expectedSessions, $created['sessionsCount']);
        self::assertNotEmpty($created['feasibilityIndicator']);

        $this->client->request('GET', sprintf('/api/training-plans/%d', $created['id']));

        self::assertResponseIsSuccessful();
        $viewed = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame($created['id'], $viewed['id']);
        self::assertCount(8, $viewed['weeks']);

        $dates = [];
        $sessionCount = 0;

        foreach ($viewed['weeks'] as $weekIndex => $week) {
            self::assertSame($weekIndex + 1, $week['week']);
            self::assertCount($sessionsPerWeek, $week['sessions']);

            foreach ($week['sessions'] as $session) {
                ++$sessionCount;
                $dates[] = $session['date'];
                self::assertGreaterThan(0, $session['plannedDurationMin']);
                self::assertGreaterThan(0, $session['plannedDistanceKm']);
                self::assertGreaterThanOrEqual(0.5, $session['plannedVmaCoef']);
                self::assertLessThanOrEqual(1.05, $session['plannedVmaCoef']);
                self::assertNotSame('', $session['description']);
                self::assertSame('planned', $session['status']);
            }
        }

        self::assertSame($expectedSessions, $sessionCount);
        $sortedDates = $dates;
        sort($sortedDates);
        self::assertSame($sortedDates, $dates);
        self::assertGreaterThanOrEqual($viewed['startDate'], $dates[0]);
        self::assertLessThanOrEqual($viewed['endDate'], $dates[array_key_last($dates)]);
        self::assertSame($expectedSessions, $this->entityManager->getRepository(Session::class)->count([]));
    }

    /** @return iterable<string, array{string, int}> */
    public static function poleProvider(): iterable
    {
        yield 'Découverte : 2 séances' => ['discovery', 2];
        yield 'Intermédiaire : 3 séances' => ['intermediate', 3];
        yield 'Performance : 5 séances' => ['performance', 5];
    }

    private function userWithProfile(): User
    {
        $user = (new User())
            ->setEmail('plan@example.com')
            ->setPseudo('plan-runner')
            ->setPassword('test-password');
        $profile = (new Profile())
            ->setFirstName('Camille')
            ->setLastName('Martin')
            ->setAge(32)
            ->setVma(15)
            ->setFcm(190)
            ->setUser($user);
        $user->setProfile($profile);

        return $user;
    }

    private function completePlanOwnedBy(User $user): TrainingPlan
    {
        $plan = (new TrainingPlan())
            ->setName('Plan PDF privé')
            ->setPoleType('discovery')
            ->setTargetType('distance')
            ->setTargetValue(5)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-09-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-26'))
            ->setDurationWeeks(8);
        $user->addTrainingPlan($plan);

        return $plan;
    }

    private function persistAlgorithmData(int $minimumPlanWeeks = 8): void
    {
        foreach ([
            'default_plan_min_weeks' => $minimumPlanWeeks,
            'default_plan_max_weeks' => 18,
            'max_sessions_discovery' => 2,
            'max_sessions_intermediate' => 3,
            'max_sessions_performance' => 5,
        ] as $key => $value) {
            $this->entityManager->persist(
                (new AlgorithmParameter())
                    ->setParameterKey($key)
                    ->setParameterValue($value),
            );
        }

        $this->entityManager->persist($this->zone('Z1', 0.50, 0.65, 50, 60));
        $this->entityManager->persist($this->zone('Z2', 0.65, 0.75, 60, 70));
        $this->entityManager->persist($this->zone('Z4', 0.85, 0.95, 80, 90));
        $this->entityManager->persist($this->zone('Z5', 0.95, 1.05, 90, 100));
    }

    /** @return array<string, int|string> */
    private function validGoal(): array
    {
        return [
            'poleType' => 'intermediate',
            'targetType' => 'distance',
            'targetValue' => 10,
            'targetUnit' => 'km',
            'terrainType' => 'road',
            'elevationTargetDPlus' => 0,
        ];
    }

    private function zone(
        string $name,
        float $vmaMin,
        float $vmaMax,
        float $fcmMin,
        float $fcmMax,
    ): IntensityZone {
        return (new IntensityZone())
            ->setName($name)
            ->setVmaCoefMin($vmaMin)
            ->setVmaCoefMax($vmaMax)
            ->setFcmPercentMin($fcmMin)
            ->setFcmPercentMax($fcmMax);
    }
}
