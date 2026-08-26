<?php

namespace App\Tests\Functional;

use App\Entity\IntensityZone;
use App\Entity\Session;
use App\Entity\SessionIntensityZone;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardIntensityZonesControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessIntensityZoneDistribution(): void
    {
        $this->client->request('GET', '/api/dashboard/intensity-zones');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testEndpointReturnsOnlyAuthenticatedUsersDistribution(): void
    {
        $z1 = $this->zone('Z1', 0.5, 0.6, 50, 60);
        $z2 = $this->zone('Z2', 0.6, 0.7, 60, 70);
        $owner = $this->userWithSessions('zones-api-owner@example.com', [
            [[$z1, 60], [$z2, 40]],
            [[$z1, 100]],
        ]);
        $otherUser = $this->userWithSessions('zones-api-other@example.com', [
            [[$z2, 100]],
        ]);
        $this->entityManager->persist($z1);
        $this->entityManager->persist($z2);
        $this->entityManager->persist($owner);
        $this->entityManager->persist($otherUser);
        $this->entityManager->flush();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/api/dashboard/intensity-zones');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame([
            'data_state' => 'READY',
            'total_sessions' => 2,
            'dominant_zone' => 'Z1',
            'zones' => [
                ['name' => 'Z1', 'label' => 'Récupération', 'sessions' => 2, 'percentage' => 66.67],
                ['name' => 'Z2', 'label' => 'Endurance', 'sessions' => 1, 'percentage' => 33.33],
            ],
            'filters' => ['period' => 'all', 'scope' => 'active'],
            'balance' => [
                'status' => 'threshold_deficit',
                'message' => 'Le plan manque de travail au seuil.',
                'actual' => ['endurance' => 100, 'threshold' => 0, 'vma' => 0],
                'reference' => ['endurance' => 66.67, 'threshold' => 33.33, 'vma' => 0],
            ],
        ], $this->responseData());
    }

    public function testEndpointReturnsAnEmptyJsonStructureForNewUser(): void
    {
        $user = (new User())
            ->setEmail('zones-api-empty@example.com')
            ->setPseudo('zones-api-empty')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/dashboard/intensity-zones');

        self::assertResponseIsSuccessful();
        self::assertSame([
            'data_state' => 'EMPTY',
            'total_sessions' => 0,
            'dominant_zone' => null,
            'zones' => [],
            'filters' => ['period' => 'all', 'scope' => 'active'],
            'balance' => [
                'status' => 'unavailable',
                'message' => 'Aucun plan actif à analyser.',
                'actual' => ['endurance' => 0, 'threshold' => 0, 'vma' => 0],
                'reference' => null,
            ],
        ], $this->responseData());
    }

    public function testEndpointRejectsInvalidFilters(): void
    {
        $user = (new User())
            ->setEmail('zones-api-filter@example.com')
            ->setPseudo('zones-api-filter')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/dashboard/intensity-zones?period=week&scope=unknown');

        self::assertResponseStatusCodeSame(422);
        self::assertSame([
            'message' => 'Les filtres demandés sont invalides.',
            'allowedPeriods' => ['30d', '3m', '6m', '1y', 'all'],
            'allowedScopes' => ['active', 'all'],
        ], $this->responseData());
    }

    public function testThirtyDayFilterRecalculatesDistributionAndKeepsOneHundredPercent(): void
    {
        $z2 = $this->zone('Z2', 0.6, 0.7, 60, 70);
        $z4 = $this->zone('Z4', 0.8, 0.9, 80, 90);
        $z5 = $this->zone('Z5', 0.9, 1.1, 90, 100);
        $user = $this->userWithSessions('zones-api-period@example.com', [
            [[$z2, 100]],
            [[$z4, 100]],
        ], '2026-08-10');
        $plan = $user->getTrainingPlans()->first();
        self::assertInstanceOf(TrainingPlan::class, $plan);
        $oldSession = (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(3)
            ->setTitle('Ancienne séance VMA')
            ->setSessionType('vma')
            ->setDate(new \DateTimeImmutable('2026-06-01'));
        $oldSession->addSessionIntensityZone(
            (new SessionIntensityZone())->setIntensityZone($z5)->setDurationPercent(100),
        );
        $plan->addSession($oldSession);

        foreach ([$z2, $z4, $z5, $user] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/dashboard/intensity-zones?period=all&scope=active');
        self::assertResponseIsSuccessful();
        self::assertSame(3, $this->responseData()['total_sessions']);

        $this->client->request('GET', '/api/dashboard/intensity-zones?period=30d&scope=active');
        self::assertResponseIsSuccessful();
        $filtered = $this->responseData();
        self::assertSame(2, $filtered['total_sessions']);
        self::assertSame(['Z2', 'Z4'], array_column($filtered['zones'], 'name'));
        self::assertSame(100, array_sum(array_column($filtered['zones'], 'percentage')));
        self::assertSame(['period' => '30d', 'scope' => 'active'], $filtered['filters']);
    }

    /**
     * @param list<list<array{IntensityZone, int}>> $sessionDistributions
     */
    private function userWithSessions(
        string $email,
        array $sessionDistributions,
        string $firstSessionDate = '2026-08-01',
    ): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');
        $plan = (new TrainingPlan())
            ->setName('Plan '.$email)
            ->setPoleType('intermediate')
            ->setTargetType('distance')
            ->setTargetValue(10)
            ->setTargetUnit('km')
            ->setTerrainType('road')
            ->setFeasibilityIndicator('BON')
            ->setStartDate(new \DateTimeImmutable('2026-08-01'))
            ->setEndDate(new \DateTimeImmutable('2026-10-01'))
            ->setDurationWeeks(8);

        foreach ($sessionDistributions as $index => $distributions) {
            $session = (new Session())
                ->setWeekIndex(1)
                ->setDayOfWeek($index + 1)
                ->setTitle('Séance '.($index + 1))
                ->setSessionType('endurance')
                ->setDate((new \DateTimeImmutable($firstSessionDate))->modify(sprintf('+%d days', $index)));

            foreach ($distributions as [$zone, $percentage]) {
                $session->addSessionIntensityZone(
                    (new SessionIntensityZone())
                        ->setIntensityZone($zone)
                        ->setDurationPercent($percentage),
                );
            }

            $plan->addSession($session);
        }

        $user->addTrainingPlan($plan);

        return $user;
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
