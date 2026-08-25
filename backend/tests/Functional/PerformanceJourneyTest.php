<?php

namespace App\Tests\Functional;

use App\Entity\AlgorithmParameter;
use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PerformanceJourneyTest extends WebTestCase
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

    public function testOwnerCanRecordPerformanceFromSessionPage(): void
    {
        [$user, $session] = $this->persistUserAndSession();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', sprintf('/training/daily/%d', $session->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Endurance fondamentale');
        self::assertSelectorExists('form.performance-form');
        $form = $crawler->selectButton('Enregistrer ma performance')->form([
            'performance[durationSec]' => '3540',
            'performance[distanceKm]' => '10.2',
            'performance[elevationDPlus]' => '80',
            'performance[terrainType]' => 'road',
            'performance[avgHr]' => '154',
            'performance[comment]' => 'Séance fluide.',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects(sprintf('/training/daily/%d', $session->getId()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'performance a bien été enregistrée');
        self::assertSelectorTextContains('#performance-title + p', 'séance est terminée');
        self::assertSame(1, $this->entityManager->getRepository(Performance::class)->count([]));

        $performance = $this->entityManager->getRepository(Performance::class)->findOneBy([]);
        self::assertInstanceOf(Performance::class, $performance);
        self::assertSame($session->getId(), $performance->getSession()?->getId());
        self::assertSame($user->getId(), $performance->getUser()?->getId());
        self::assertSame(3540, $performance->getDurationSec());
        self::assertSame(10.2, $performance->getDistanceKm());
        self::assertSame('done', $performance->getSession()?->getStatus());
    }

    public function testInvalidPerformanceIsRejectedAndNotPersisted(): void
    {
        [$user, $session] = $this->persistUserAndSession();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', sprintf('/training/daily/%d', $session->getId()));
        $form = $crawler->selectButton('Enregistrer ma performance')->form([
            'performance[durationSec]' => '0',
            'performance[distanceKm]' => '-1',
            'performance[elevationDPlus]' => '-5',
            'performance[terrainType]' => 'road',
            'performance[avgHr]' => '250',
            'performance[comment]' => '',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form.performance-form', 'doit être supérieur à zéro');
        self::assertSame(0, $this->entityManager->getRepository(Performance::class)->count([]));
    }

    public function testUserCannotViewOrRecordAnotherUsersSession(): void
    {
        [, $session] = $this->persistUserAndSession();
        $otherUser = (new User())
            ->setEmail('other-performance@example.com')
            ->setPseudo('other-performance-runner')
            ->setPassword('test-password');
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->request('GET', sprintf('/training/daily/%d', $session->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testPerformanceApiCreatesPerformanceForOwnedSession(): void
    {
        [$user, $session] = $this->persistUserAndSession();
        $this->client->loginUser($user);

        $this->client->jsonRequest('POST', '/api/performances', [
            'sessionId' => $session->getId(),
            'durationSec' => 3480,
            'distanceKm' => 10.4,
            'elevationDPlus' => 65,
            'terrainType' => 'road',
            'avgHr' => 152,
            'comment' => 'Bonne maîtrise de l’allure.',
        ]);

        self::assertResponseStatusCodeSame(201);
        $response = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame($session->getId(), $response['sessionId']);
        self::assertSame(10.4, $response['distanceKm']);
        self::assertSame(3480, $response['durationSec']);
        self::assertSame('PERFORMANCE_SUPERIEURE', $response['evaluation']['result']);
        self::assertGreaterThan(1, $response['evaluation']['speedRatio']);
        self::assertSame('INCREASE_LOAD', $response['adaptation']['decision']);
        self::assertSame(1.05, $response['adaptation']['loadFactor']);
        self::assertSame(100, $response['adaptation']['progressScore']);
        self::assertSame(0, $response['adaptation']['adjustedSessionsCount']);
        self::assertSame(1, $this->entityManager->getRepository(Performance::class)->count([]));

        $this->client->jsonRequest('POST', '/api/performances', [
            'sessionId' => $session->getId(),
            'durationSec' => 3480,
            'distanceKm' => 10.4,
            'terrainType' => 'road',
        ]);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, $this->entityManager->getRepository(Performance::class)->count([]));
    }

    public function testPerformanceApiRejectsInvalidDataAndAnotherUsersSession(): void
    {
        [, $session] = $this->persistUserAndSession();
        $otherUser = (new User())
            ->setEmail('api-other@example.com')
            ->setPseudo('api-other-runner')
            ->setPassword('test-password');
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($otherUser);

        $this->client->jsonRequest('POST', '/api/performances', [
            'sessionId' => $session->getId(),
            'durationSec' => 3600,
            'distanceKm' => 10,
            'terrainType' => 'road',
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->entityManager->getRepository(Performance::class)->count([]));

        $this->client->jsonRequest('POST', '/api/performances', [
            'sessionId' => 'not-an-integer',
            'durationSec' => 0,
            'distanceKm' => -1,
            'terrainType' => 'track',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Performance::class)->count([]));
    }

    public function testSuperiorPerformanceImprovesScoreAndIncreasesFutureLoad(): void
    {
        $this->assertCompleteAdaptationScenario(
            initialScore: 50,
            actualDistance: 10.5,
            expectedEvaluation: 'PERFORMANCE_SUPERIEURE',
            expectedDecision: 'INCREASE_LOAD',
            expectedScore: 100,
            expectedDuration: 44,
            expectedDistance: 8.8,
            expectedElevation: 110,
            expectedVmaCoefficient: 0.99,
        );
    }

    public function testInsufficientPerformanceReducesScoreAndFutureLoad(): void
    {
        $this->assertCompleteAdaptationScenario(
            initialScore: 100,
            actualDistance: 8.5,
            expectedEvaluation: 'PERFORMANCE_INSUFFISANTE',
            expectedDecision: 'REDUCE_LOAD',
            expectedScore: 84.33,
            expectedDuration: 36,
            expectedDistance: 7.2,
            expectedElevation: 90,
            expectedVmaCoefficient: 0.81,
        );
    }

    /** @return array{User, Session} */
    private function persistUserAndSession(): array
    {
        $user = (new User())
            ->setEmail('performance@example.com')
            ->setPseudo('performance-runner')
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Préparation 10 km')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-31'))
            ->setEndDate(new \DateTimeImmutable('2026-10-25'))
            ->setDurationWeeks(8);
        $session = (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(2)
            ->setTitle('Endurance fondamentale')
            ->setDescription('Courez à une allure confortable et régulière.')
            ->setSessionType('endurance')
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setPlannedVmaCoef(0.7)
            ->setPlannedFcmZone('Z2')
            ->setDate(new \DateTimeImmutable('2026-09-01'))
            ->setStatus('planned');
        $plan->addSession($session);
        $user->addTrainingPlan($plan);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $session];
    }

    private function assertCompleteAdaptationScenario(
        float $initialScore,
        float $actualDistance,
        string $expectedEvaluation,
        string $expectedDecision,
        float $expectedScore,
        int $expectedDuration,
        float $expectedDistance,
        int $expectedElevation,
        float $expectedVmaCoefficient,
    ): void {
        [$user, $plan, $currentSession, $pastSession, $futureSession, $goalEvent] =
            $this->persistCompleteBlock(
                $initialScore,
                $expectedDecision === 'INCREASE_LOAD',
            );
        $futureDate = $futureSession->getDate()?->format('Y-m-d');
        $goalDuration = $goalEvent->getPlannedDurationMin();
        $planId = $plan->getId();
        $currentSessionId = $currentSession->getId();
        $pastSessionId = $pastSession->getId();
        $futureSessionId = $futureSession->getId();
        $goalEventId = $goalEvent->getId();
        $this->client->loginUser($user);

        $this->client->jsonRequest('POST', '/api/performances', [
            'sessionId' => $currentSession->getId(),
            'durationSec' => 3600,
            'distanceKm' => $actualDistance,
            'elevationDPlus' => 0,
            'terrainType' => 'road',
        ]);

        self::assertResponseStatusCodeSame(201);
        $response = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame($expectedEvaluation, $response['evaluation']['result']);
        self::assertSame($expectedDecision, $response['adaptation']['decision']);
        self::assertSame(
            $expectedDecision === 'INCREASE_LOAD' ? 1.1 : 0.9,
            $response['adaptation']['loadFactor'],
        );
        self::assertSame($expectedScore, (float) $response['adaptation']['progressScore']);
        self::assertSame(1, $response['adaptation']['adjustedSessionsCount']);
        self::assertSame(
            $expectedDecision === 'INCREASE_LOAD' ? 100 : 66.67,
            $response['adaptation']['successRate'],
        );
        self::assertSame(80, $response['adaptation']['successValidationRate']);
        self::assertStringContainsString('%', $response['adaptation']['reason']);
        self::assertNotSame('', $response['adaptation']['modification']);

        $this->entityManager->clear();
        $storedPlan = $this->entityManager->find(TrainingPlan::class, $planId);
        $storedCurrent = $this->entityManager->find(Session::class, $currentSessionId);
        $storedPast = $this->entityManager->find(Session::class, $pastSessionId);
        $storedFuture = $this->entityManager->getRepository(Session::class)->findOneBy([
            'trainingPlan' => $storedPlan,
            'date' => new \DateTimeImmutable((string) $futureDate),
        ]);
        $storedGoal = $this->entityManager->find(Session::class, $goalEventId);

        self::assertSame((float) $expectedScore, $storedPlan?->getProgressScore());
        self::assertSame('done', $storedCurrent?->getStatus());
        self::assertSame(40, $storedPast?->getPlannedDurationMin());
        self::assertNull($this->entityManager->find(Session::class, $futureSessionId));
        self::assertSame($futureDate, $storedFuture?->getDate()?->format('Y-m-d'));
        self::assertSame($expectedDuration, $storedFuture?->getPlannedDurationMin());
        self::assertSame($expectedDistance, $storedFuture?->getPlannedDistanceKm());
        self::assertSame($expectedElevation, $storedFuture?->getPlannedElevationDPlus());
        self::assertSame($expectedVmaCoefficient, $storedFuture?->getPlannedVmaCoef());
        self::assertSame($goalDuration, $storedGoal?->getPlannedDurationMin());
        self::assertSame(1, $this->entityManager->getRepository(Performance::class)->count([]));
        $history = $storedPlan?->getAdaptationHistory()[0] ?? null;
        self::assertNotNull($history);
        self::assertSame($expectedDecision, $history['decision']);
        self::assertStringContainsString('%', $history['reason']);
        self::assertNotSame('', $history['modification']);
    }

    /** @return array{User, TrainingPlan, Session, Session, Session, Session} */
    private function persistCompleteBlock(float $initialScore, bool $successfulBlock): array
    {
        $user = (new User())
            ->setEmail(sprintf('block-%s@example.com', (string) $initialScore))
            ->setPseudo(sprintf('block-runner-%s', (string) $initialScore))
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Plan adaptatif 10 km')
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-17'))
            ->setEndDate(new \DateTimeImmutable('2026-10-11'))
            ->setDurationWeeks(8)
            ->setProgressScore($initialScore);
        $pastSession = $this->adaptationSession(1, '2026-08-18', 'done', 'endurance');
        $secondPastSession = $this->adaptationSession(
            2,
            '2026-08-25',
            $successfulBlock ? 'done' : 'missed',
            'endurance',
        );
        $currentSession = $this->adaptationSession(3, '2026-09-01', 'planned', 'threshold')
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60);
        $futureSession = $this->adaptationSession(4, '2026-09-08', 'planned', 'threshold');
        $goalEvent = $this->adaptationSession(8, '2026-10-11', 'planned', 'goal_event');

        foreach ([$pastSession, $secondPastSession, $currentSession, $futureSession, $goalEvent] as $session) {
            $plan->addSession($session);
        }

        $user->addTrainingPlan($plan);
        $successParameter = (new AlgorithmParameter())
            ->setParameterKey('success_validation_rate')
            ->setParameterValue(80);
        $progressionParameter = (new AlgorithmParameter())
            ->setParameterKey('progression_max_percent')
            ->setParameterValue(10);
        $recoveryParameter = (new AlgorithmParameter())
            ->setParameterKey('recovery_week_frequency')
            ->setParameterValue(4);
        $this->entityManager->persist($successParameter);
        $this->entityManager->persist($progressionParameter);
        $this->entityManager->persist($recoveryParameter);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return [$user, $plan, $currentSession, $pastSession, $futureSession, $goalEvent];
    }

    private function adaptationSession(
        int $week,
        string $date,
        string $status,
        string $type,
    ): Session {
        return (new Session())
            ->setWeekIndex($week)
            ->setDayOfWeek(2)
            ->setTitle('Séance semaine '.$week)
            ->setDescription('Consignes de la séance.')
            ->setSessionType($type)
            ->setPlannedDistanceKm($type === 'goal_event' ? 10 : 8)
            ->setPlannedDurationMin($type === 'goal_event' ? 60 : 40)
            ->setPlannedElevationDPlus(100)
            ->setPlannedVmaCoef(0.9)
            ->setPlannedFcmZone('Z4')
            ->setDate(new \DateTimeImmutable($date))
            ->setStatus($status);
    }
}
