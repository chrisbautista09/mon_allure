<?php

namespace App\Tests\Functional;

use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminTrainingPlanControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testAnonymousAndRegularUsersCannotMonitorPlans(): void
    {
        $this->client->request('GET', '/api/admin/training-plans');
        self::assertResponseRedirects('http://localhost/login');

        $this->client->loginUser($this->persistUser('regular-monitoring@example.com'));
        $this->client->request('GET', '/api/admin/training-plans');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorReceivesStructuredPaginatedPlans(): void
    {
        $administrator = $this->persistUser('admin-monitoring@example.com', ['ROLE_ADMIN']);
        $runner = $this->persistUser('runner-monitoring@example.com', pseudo: 'Runner81');
        $this->persistPlan($runner, 'Plan récent', 'OPTIMAL', 82, true, '2026-08-03');
        $this->persistPlan($runner, 'Plan ancien', 'MOYEN', 55, false, '2026-08-01');
        $this->entityManager->flush();
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/training-plans?page=1&perPage=1');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = $this->responseData();
        self::assertCount(1, $response['items']);
        self::assertSame([
            'page' => 1,
            'perPage' => 1,
            'total' => 2,
            'totalPages' => 2,
        ], $response['pagination']);
        $plan = $response['items'][0];
        self::assertSame('Plan récent', $plan['name']);
        self::assertSame('Runner81', $plan['user']);
        self::assertSame('runner-monitoring@example.com', $plan['user_email']);
        self::assertSame('OPTIMAL', $plan['feasibility_indicator']);
        self::assertSame('OPTIMAL', $plan['feasibility_status']);
        self::assertSame(82, $plan['progress_score']);
        self::assertSame('distance', $plan['target_type']);
        self::assertSame(10, $plan['target_value']);
        self::assertSame('km', $plan['target_unit']);
        self::assertSame('road', $plan['terrain_type']);
        self::assertSame(5, $plan['current_week']);
        self::assertSame(12, $plan['duration_weeks']);
        self::assertSame('ACTIVE', $plan['status']);
        self::assertArrayHasKey('training_load', $plan);
        self::assertArrayHasKey('success_rate', $plan);
        self::assertFalse($plan['is_problematic']);
        self::assertSame(0, $plan['anomaly_count']);
        self::assertArrayHasKey('anomalies', $plan);

        $this->client->request('GET', '/api/admin/training-plans?page=2&perPage=1');
        self::assertResponseIsSuccessful();
        $acceptablePlan = $this->responseData()['items'][0];
        self::assertSame('MOYEN', $acceptablePlan['feasibility_indicator']);
        self::assertSame('ACCEPTABLE', $acceptablePlan['feasibility_status']);
    }

    public function testInvalidPaginationReturnsUnprocessableEntity(): void
    {
        $this->client->loginUser($this->persistUser('admin-invalid-monitoring@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/api/admin/training-plans?page=0&perPage=101');

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('message', $this->responseData());
    }

    public function testWeakFeasibilityIsExposedAsCriticalVisualStatus(): void
    {
        $administrator = $this->persistUser('admin-critical-monitoring@example.com', ['ROLE_ADMIN']);
        $runner = $this->persistUser('runner-critical-monitoring@example.com');
        $this->persistPlan($runner, 'Plan critique', 'FAIBLE', 25, true, '2026-08-04');
        $this->entityManager->flush();
        $this->client->loginUser($administrator);

        $this->client->request('GET', '/api/admin/training-plans');

        self::assertResponseIsSuccessful();
        $plan = $this->responseData()['items'][0];
        self::assertSame('FAIBLE', $plan['feasibility_indicator']);
        self::assertSame('IMPOSSIBLE', $plan['feasibility_status']);
        self::assertTrue($plan['is_problematic']);
        self::assertContains('IMPOSSIBLE_FEASIBILITY', $plan['anomalies']);
        self::assertCount(2, $plan['monitoring_history']);
        self::assertFalse($plan['monitoring_history'][0]['resolved']);

        $this->client->request('GET', '/api/admin/training-plans?status=IMPOSSIBLE');
        self::assertResponseIsSuccessful();
        $filteredPlan = $this->responseData()['items'][0];
        self::assertCount(2, $filteredPlan['monitoring_history']);
    }

    public function testAdministratorCanCombineFeasibilityUserAndPlanStatusFilters(): void
    {
        $administrator = $this->persistUser('admin-filter-plans@example.com', ['ROLE_ADMIN']);
        $runner = $this->persistUser('runner-filter-plans@example.com', pseudo: 'RunnerFilter');
        $otherRunner = $this->persistUser('other-filter-plans@example.com');
        $this->persistPlan($runner, 'Plan recherché', 'MOYEN', 55, false, '2026-08-03');
        $this->persistPlan($otherRunner, 'Plan exclu', 'MOYEN', 55, false, '2026-08-02');
        $this->entityManager->flush();
        $this->client->loginUser($administrator);

        $this->client->request(
            'GET',
            '/api/admin/training-plans?feasibility=ACCEPTABLE&user=RunnerFilter&status=ARCHIVED',
        );

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertCount(1, $response['items']);
        self::assertSame('Plan recherché', $response['items'][0]['name']);
        self::assertSame('ARCHIVED', $response['items'][0]['status']);
    }

    public function testInvalidMonitoringFilterReturnsUnprocessableEntity(): void
    {
        $this->client->loginUser($this->persistUser('admin-invalid-filter-plans@example.com', ['ROLE_ADMIN']));

        $this->client->request('GET', '/api/admin/training-plans?feasibility=UNKNOWN');

        self::assertResponseStatusCodeSame(422);
    }

    public function testAdministratorFiltersChosenAthleteAndSeesImpossiblePlanFlagged(): void
    {
        $administrator = $this->persistUser('admin-final-monitoring@example.com', ['ROLE_ADMIN']);
        $chosenRunner = $this->persistUser('chosen-runner@example.com', pseudo: 'ChosenRunner');
        $otherRunner = $this->persistUser('other-runner@example.com', pseudo: 'OtherRunner');
        $this->persistPlan($chosenRunner, 'Plan incohérent choisi', 'IMPOSSIBLE', 60, true, '2026-08-05');
        $this->persistPlan($otherRunner, 'Plan incohérent exclu', 'IMPOSSIBLE', 60, true, '2026-08-04');
        $this->entityManager->flush();
        $this->client->loginUser($administrator);

        $this->client->request(
            'GET',
            '/api/admin/training-plans?feasibility=IMPOSSIBLE&user=chosen-runner@example.com',
        );

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(1, $response['pagination']['total']);
        self::assertCount(1, $response['items']);
        $plan = $response['items'][0];
        self::assertSame('ChosenRunner', $plan['user']);
        self::assertSame('Plan incohérent choisi', $plan['name']);
        self::assertSame('IMPOSSIBLE', $plan['feasibility_indicator']);
        self::assertTrue($plan['is_problematic']);
        self::assertSame(1, $plan['anomaly_count']);
        self::assertSame(['IMPOSSIBLE_FEASIBILITY'], $plan['anomalies']);
    }

    private function persistUser(string $email, array $roles = [], ?string $pseudo = null): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo($pseudo ?? str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password')
            ->setRoles($roles);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function persistPlan(
        User $user,
        string $name,
        string $feasibility,
        float $progress,
        bool $active,
        string $createdAt,
    ): void {
        $this->entityManager->persist((new TrainingPlan())
            ->setName($name)
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator($feasibility)
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-24'))
            ->setDurationWeeks(12)
            ->setCurrentWeek(5)
            ->setProgressScore($progress)
            ->setIsActive($active)
            ->setCreatedAt(new \DateTimeImmutable($createdAt))
            ->setUser($user));
    }

    /** @return array<string, mixed> */
    private function responseData(): array
    {
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
