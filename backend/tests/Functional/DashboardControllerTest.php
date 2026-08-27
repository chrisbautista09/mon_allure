<?php

namespace App\Tests\Functional;

use App\Entity\Performance;
use App\Entity\Session;
use App\Entity\TrainingPlan;
use App\Entity\User;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DashboardControllerTest extends WebTestCase
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

    public function testAnonymousUserCannotRetrieveFormStatus(): void
    {
        $this->client->request('GET', '/api/dashboard/form-status');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAnonymousUserCannotRetrieveWeather(): void
    {
        $this->client->request('GET', '/api/dashboard/weather?latitude=43.60&longitude=1.44');

        self::assertResponseRedirects('http://localhost/login');
    }

    public function testAuthenticatedUserCanRetrieveWeatherAsJson(): void
    {
        $user = $this->persistUser('weather@example.com');
        $this->replaceWeatherService(new MockResponse(json_encode([
            'current' => [
                'temperature_2m' => 18,
                'relative_humidity_2m' => 65,
                'wind_speed_10m' => 12,
                'weather_code' => 2,
            ],
        ], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type: application/json'],
        ]));
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/dashboard/weather', [
            'latitude' => '43.60',
            'longitude' => '1.44',
            'location' => 'Toulouse',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame([
            'location' => 'Toulouse',
            'temperature' => 18,
            'condition' => 'Partiellement nuageux',
            'wind' => 12,
            'advice' => 'Les conditions sont adaptées à votre séance prévue.',
            'freshness' => 'FRESH',
        ], $this->responseData());
    }

    public function testWeatherEndpointRequiresCoordinates(): void
    {
        $this->client->loginUser($this->persistUser('weather-location@example.com'));

        $this->client->request('GET', '/api/dashboard/weather');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('LOCATION_REQUIRED', $this->responseData()['dataState']);
    }

    public function testWeatherEndpointRejectsInvalidCoordinates(): void
    {
        $this->client->loginUser($this->persistUser('weather-invalid@example.com'));

        $this->client->request('GET', '/api/dashboard/weather', [
            'latitude' => '91',
            'longitude' => '1.44',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('INVALID_LOCATION', $this->responseData()['dataState']);
    }

    public function testWeatherEndpointHandlesProviderErrors(): void
    {
        $user = $this->persistUser('weather-unavailable@example.com');
        $this->replaceWeatherService(new MockResponse('Service unavailable', ['http_code' => 503]));
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/dashboard/weather', [
            'latitude' => '43.60',
            'longitude' => '1.44',
        ]);

        self::assertResponseStatusCodeSame(503);
        $response = $this->responseData();
        self::assertSame('UNAVAILABLE', $response['dataState']);
        self::assertStringContainsString('dashboard reste accessible', $response['message']);
    }

    public function testWeatherEndpointDisplaysLastKnownDataWhenRefreshFails(): void
    {
        $this->client->disableReboot();
        $user = $this->persistUser('weather-stale@example.com');
        $clock = new MockClock('2026-08-26 10:00:00');
        $cache = new ArrayAdapter(clock: $clock);
        $success = new MockResponse(json_encode([
            'current' => [
                'temperature_2m' => 18,
                'relative_humidity_2m' => 65,
                'wind_speed_10m' => 12,
                'weather_code' => 2,
            ],
        ], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type: application/json'],
        ]);
        self::getContainer()->set(WeatherService::class, new WeatherService(
            new MockHttpClient([
                $success,
                new MockResponse('Service unavailable', ['http_code' => 503]),
            ]),
            $cache,
            $clock,
        ));
        $this->client->loginUser($user);
        $parameters = ['latitude' => '43.60', 'longitude' => '1.44'];

        $this->client->request('GET', '/api/dashboard/weather', $parameters);
        self::assertResponseIsSuccessful();
        self::assertSame('FRESH', $this->responseData()['freshness']);

        $clock->sleep(3601);
        $this->client->request('GET', '/api/dashboard/weather', $parameters);

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(18, $response['temperature']);
        self::assertSame('STALE', $response['freshness']);
    }

    public function testAuthenticatedUserWithoutPlanReceivesExplicitEmptyState(): void
    {
        $user = $this->persistUser('no-plan@example.com');
        $this->client->loginUser($user);

        $this->client->request('GET', '/api/dashboard/form-status');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        $response = $this->responseData();
        self::assertNull($response['score']);
        self::assertNull($response['status']);
        self::assertNull($response['label']);
        self::assertSame('NO_ACTIVE_PLAN', $response['dataState']);
        self::assertSame('État de forme indisponible', $response['dataStateLabel']);
        self::assertSame('UNKNOWN', $response['trend']);
        self::assertSame(0, $response['recentPerformanceCount']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $response['last_update']);
    }

    public function testEndpointReturnsOnlyAuthenticatedUsersFormStatus(): void
    {
        $owner = $this->persistUser('dashboard-owner@example.com', 82);
        $this->addPerformance($owner, 82);
        $this->persistUser('dashboard-other@example.com', 15);
        $this->client->loginUser($owner);

        $this->client->request('GET', '/api/dashboard/form-status');

        self::assertResponseIsSuccessful();
        $response = $this->responseData();
        self::assertSame(82, $response['score']);
        self::assertSame('GOOD', $response['status']);
        self::assertSame('Bonne forme', $response['label']);
        self::assertSame('LIMITED_DATA', $response['dataState']);
        self::assertSame('UNKNOWN', $response['trend']);
        self::assertSame('données insuffisantes', $response['trendLabel']);
        self::assertArrayHasKey('trendMessage', $response);
        self::assertArrayHasKey('calculatedAt', $response);
        self::assertSame(substr($response['calculatedAt'], 0, 10), $response['last_update']);
    }

    private function persistUser(string $email, ?float $progressScore = null): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setPseudo(str_replace(['@', '.'], '-', $email))
            ->setPassword('test-password');

        if ($progressScore !== null) {
            $user->addTrainingPlan((new TrainingPlan())
                ->setName('Plan dashboard')
                ->setPoleType('intermediate')
                ->setTargetType('distance')
                ->setTargetValue(10)
                ->setTargetUnit('km')
                ->setTerrainType('road')
                ->setFeasibilityIndicator('BON')
                ->setStartDate(new \DateTimeImmutable('2026-08-01'))
                ->setEndDate(new \DateTimeImmutable('2026-10-01'))
                ->setDurationWeeks(8)
                ->setProgressScore($progressScore));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function addPerformance(User $user, float $score): void
    {
        $plan = $user->getTrainingPlans()->first();
        self::assertInstanceOf(TrainingPlan::class, $plan);
        $session = (new Session())
            ->setWeekIndex(1)
            ->setDayOfWeek(1)
            ->setTitle('Séance dashboard')
            ->setSessionType('endurance')
            ->setPlannedDistanceKm(10)
            ->setPlannedDurationMin(60)
            ->setDate(new \DateTimeImmutable('today'));
        $performance = (new Performance())
            ->setDistanceKm($score / 10)
            ->setDurationSec(3600)
            ->setCreatedAt(new \DateTimeImmutable());
        $plan->addSession($session);
        $session->setPerformance($performance);
        $user->addPerformance($performance);
        $this->entityManager->flush();
    }

    private function replaceWeatherService(MockResponse $response): void
    {
        $clock = new MockClock('2026-08-26 10:00:00');
        self::getContainer()->set(WeatherService::class, new WeatherService(
            new MockHttpClient($response),
            new ArrayAdapter(clock: $clock),
            $clock,
        ));
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
