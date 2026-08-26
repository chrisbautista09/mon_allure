<?php

namespace App\Tests\Functional;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PerformanceHistoryControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotAccessPerformanceHistory(): void
    {
        $this->client->request('GET', '/api/performances/history');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testEndpointReturnsOnlyOwnersPerformancesChronologically(): void
    {
        $owner = $this->persistUserWithPerformances('performance-history-owner@example.com', [
            ['2026-08-20', 8.2, 3100, null],
            ['2026-08-25', 10.4, 3600, 125],
        ]);
        $this->persistUserWithPerformances('performance-history-other@example.com', [
            ['2026-08-22', 99.0, 9999, 999],
        ]);
        $this->client->loginUser($owner);

        $this->client->request('GET', '/api/performances/history');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $history = $this->responseData();
        self::assertSame(['2026-08-20', '2026-08-25'], array_column($history, 'date'));
        self::assertSame([8.2, 10.4], array_column($history, 'distance'));
        self::assertSame([3100, 3600], array_column($history, 'time'));
        self::assertSame([null, 125], array_column($history, 'elevation'));
        self::assertArrayHasKey('planned', $history[0]);
        self::assertArrayHasKey('comparison', $history[0]);
    }

    public function testEndpointReturnsAnEmptyJsonListForNewUser(): void
    {
        $user = (new User())
            ->setEmail('empty-performance-history@example.com')
            ->setPseudo('empty-performance-history')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/performances/history');

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->responseData());
    }

    public function testEndpointFiltersPerformancesByRequestedPeriod(): void
    {
        $user = $this->persistUserWithPerformances('performance-period-owner@example.com', [
            ['-40 days', 6.0, 2400, 40],
            ['-10 days', 8.0, 3000, 80],
            ['-2 days', 10.0, 3600, 120],
        ]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/performances/history?period=30d');

        self::assertResponseIsSuccessful();
        $history = $this->responseData();
        self::assertCount(2, $history);
        self::assertSame([8, 10], array_column($history, 'distance'));

        $this->client->request('GET', '/api/performances/history?period=7d');
        self::assertResponseIsSuccessful();
        self::assertSame([10], array_column($this->responseData(), 'distance'));

        $this->client->request('GET', '/api/performances/history?period=all');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->responseData());
    }

    public function testEndpointRejectsUnknownPeriod(): void
    {
        $user = (new User())
            ->setEmail('invalid-performance-period@example.com')
            ->setPseudo('invalid-performance-period')
            ->setPassword('test-password');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/performances/history?period=decade');

        self::assertResponseStatusCodeSame(422);
        $response = $this->responseData();
        self::assertSame('La période demandée est invalide.', $response['message']);
        self::assertSame(['7d', '30d', '3m', '6m', '1y', 'all'], $response['allowedPeriods']);
    }

    public function testEndpointReturnsThreeHundredPerformancesInStableOrder(): void
    {
        $performanceData = [];

        for ($index = 1; $index <= 300; ++$index) {
            $performanceData[] = [
                (new \DateTimeImmutable('2025-10-01'))->modify(sprintf('+%d days', $index - 1))->format('Y-m-d'),
                (float) $index,
                3600 + $index,
                $index - 1,
            ];
        }

        $user = $this->persistUserWithPerformances('performance-history-300@example.com', $performanceData);
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/performances/history?period=all');

        self::assertResponseIsSuccessful();
        $history = $this->responseData();
        self::assertCount(300, $history);
        self::assertSame(1, $history[0]['distance']);
        self::assertSame(300, $history[299]['distance']);
        self::assertSame('2025-10-01', $history[0]['date']);
        self::assertSame(
            (new \DateTimeImmutable('2025-10-01'))->modify('+299 days')->format('Y-m-d'),
            $history[299]['date'],
        );
    }

    /** @param list<array{string, float, int, int|null}> $performanceData */
    private function persistUserWithPerformances(string $email, array $performanceData): User
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

        foreach ($performanceData as $index => [$date, $distance, $time, $elevation]) {
            $session = (new Session())
                ->setWeekIndex(1)
                ->setDayOfWeek(($index % 7) + 1)
                ->setTitle('Séance '.($index + 1))
                ->setSessionType('endurance')
                ->setPlannedDistanceKm(10)
                ->setPlannedDurationMin(60)
                ->setPlannedElevationDPlus(100)
                ->setDate(new \DateTimeImmutable($date))
                ->setStatus('completed');
            $performance = (new Performance())
                ->setDistanceKm($distance)
                ->setDurationSec($time)
                ->setElevationDPlus($elevation);
            $plan->addSession($session);
            $session->setPerformance($performance);
            $user->addPerformance($performance);
        }

        $user->addTrainingPlan($plan);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /** @return list<array<string, mixed>> */
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
