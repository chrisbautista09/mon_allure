<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use App\Entity\IntensityZone;
use App\Entity\Profile;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
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
        self::assertSelectorTextContains('h3', 'Endurance fondamentale');
        self::assertSelectorTextContains('article p:last-child', 'Cible');
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

    private function persistAlgorithmData(): void
    {
        foreach ([
            'default_plan_min_weeks' => 8,
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
