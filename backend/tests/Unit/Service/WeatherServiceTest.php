<?php

namespace App\Tests\Unit\Service;

use App\Exception\WeatherUnavailableException;
use App\Service\WeatherService;
use Symfony\Component\Clock\MockClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeatherServiceTest extends TestCase
{
    public function testReturnsNormalizedApplicationWeatherData(): void
    {
        $client = new MockHttpClient(
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('GET', $method);
                self::assertStringStartsWith('https://api.open-meteo.com/v1/forecast?', $url);
                self::assertSame(45.76, $options['query']['latitude']);
                self::assertSame(4.84, $options['query']['longitude']);
                self::assertSame('celsius', $options['query']['temperature_unit']);
                self::assertSame('kmh', $options['query']['wind_speed_unit']);

                return new MockResponse(json_encode([
                    'current' => [
                        'temperature_2m' => 18.2,
                        'relative_humidity_2m' => 65,
                        'wind_speed_10m' => 15.0,
                        'weather_code' => 3,
                    ],
                ], JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            },
            'https://api.open-meteo.com',
        );

        self::assertSame([
            'temperature' => 18.2,
            'humidity' => 65,
            'wind' => 15.0,
            'condition' => 'Nuageux',
            'freshness' => 'FRESH',
        ], $this->service($client)->getWeather(45.76, 4.84));
    }

    #[DataProvider('conditionProvider')]
    public function testMapsOpenMeteoCodesToApplicationConditions(int $code, string $condition): void
    {
        $response = new MockResponse(json_encode([
            'current' => [
                'temperature_2m' => 12,
                'relative_humidity_2m' => 70,
                'wind_speed_10m' => 8,
                'weather_code' => $code,
            ],
        ], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type: application/json'],
        ]);

        $result = $this->service(new MockHttpClient($response))->getWeather(0, 0);

        self::assertSame($condition, $result['condition']);
    }

    /** @return iterable<string, array{int, string}> */
    public static function conditionProvider(): iterable
    {
        yield 'clear sky' => [0, 'Ciel dégagé'];
        yield 'partly cloudy' => [2, 'Partiellement nuageux'];
        yield 'overcast' => [3, 'Nuageux'];
        yield 'fog' => [45, 'Brouillard'];
        yield 'drizzle' => [53, 'Bruine'];
        yield 'rain' => [63, 'Pluie'];
        yield 'snow' => [75, 'Neige'];
        yield 'thunderstorm' => [95, 'Orage'];
        yield 'unknown provider code' => [999, 'Condition inconnue'];
    }

    public function testWrapsApiErrorsInAnApplicationException(): void
    {
        $client = new MockHttpClient(new MockResponse('Service unavailable', ['http_code' => 503]));

        try {
            $this->service($client)->getWeather(45.76, 4.84);
            self::fail('A WeatherUnavailableException should have been thrown.');
        } catch (WeatherUnavailableException $exception) {
            self::assertSame('Les données météo sont temporairement indisponibles.', $exception->getMessage());
            self::assertNotNull($exception->getPrevious());
        }
    }

    public function testRejectsAnIncompleteProviderResponse(): void
    {
        $client = new MockHttpClient(new MockResponse('{"current":{"temperature_2m":18}}', [
            'response_headers' => ['content-type: application/json'],
        ]));

        $this->expectException(WeatherUnavailableException::class);
        $this->expectExceptionMessage('La réponse météo reçue est incomplète.');

        $this->service($client)->getWeather(45.76, 4.84);
    }

    #[DataProvider('invalidCoordinateProvider')]
    public function testRejectsInvalidCoordinates(float $latitude, float $longitude): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('The provider must not be called for invalid coordinates.');
        });

        $this->expectException(\InvalidArgumentException::class);

        $this->service($client)->getWeather($latitude, $longitude);
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidCoordinateProvider(): iterable
    {
        yield 'latitude too low' => [-90.1, 0.0];
        yield 'latitude too high' => [90.1, 0.0];
        yield 'longitude too low' => [0.0, -180.1];
        yield 'longitude too high' => [0.0, 180.1];
        yield 'non finite latitude' => [INF, 0.0];
    }

    public function testCachesWeatherByRoundedPosition(): void
    {
        $requestCount = 0;
        $client = new MockHttpClient(static function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return new MockResponse(json_encode([
                'current' => [
                    'temperature_2m' => 18,
                    'relative_humidity_2m' => 65,
                    'wind_speed_10m' => 15,
                    'weather_code' => 0,
                ],
            ], JSON_THROW_ON_ERROR), [
                'response_headers' => ['content-type: application/json'],
            ]);
        });
        $service = $this->service($client);

        $firstResult = $service->getWeather(45.761, 4.841);
        $secondResult = $service->getWeather(45.762, 4.842);

        self::assertSame($firstResult, $secondResult);
        self::assertSame(1, $requestCount);
    }

    public function testRefreshesWeatherAfterOneHour(): void
    {
        $clock = new MockClock('2026-08-26 10:00:00');
        $cache = new ArrayAdapter(clock: $clock);
        $requestCount = 0;
        $client = new MockHttpClient(static function () use (&$requestCount): MockResponse {
            ++$requestCount;

            return self::weatherResponse(18 + $requestCount);
        });
        $service = new WeatherService($client, $cache, $clock);

        self::assertSame(19.0, $service->getWeather(45.76, 4.84)['temperature']);
        $clock->sleep(3590);
        self::assertSame(19.0, $service->getWeather(45.76, 4.84)['temperature']);
        self::assertSame(1, $requestCount);
        $clock->sleep(11);
        self::assertSame(20.0, $service->getWeather(45.76, 4.84)['temperature']);
        self::assertSame(2, $requestCount);
    }

    public function testReturnsLastKnownWeatherWhenRefreshFails(): void
    {
        $clock = new MockClock('2026-08-26 10:00:00');
        $cache = new ArrayAdapter(clock: $clock);
        $client = new MockHttpClient([
            self::weatherResponse(18),
            new MockResponse('Service unavailable', ['http_code' => 503]),
        ]);
        $service = new WeatherService($client, $cache, $clock);

        self::assertSame('FRESH', $service->getWeather(45.76, 4.84)['freshness']);
        $clock->sleep(3601);
        $weather = $service->getWeather(45.76, 4.84);

        self::assertSame(18.0, $weather['temperature']);
        self::assertSame('STALE', $weather['freshness']);
    }

    private function service(HttpClientInterface $client): WeatherService
    {
        $clock = new MockClock('2026-08-26 10:00:00');

        return new WeatherService($client, new ArrayAdapter(clock: $clock), $clock);
    }

    private static function weatherResponse(float $temperature): MockResponse
    {
        return new MockResponse(json_encode([
            'current' => [
                'temperature_2m' => $temperature,
                'relative_humidity_2m' => 65,
                'wind_speed_10m' => 15,
                'weather_code' => 0,
            ],
        ], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type: application/json'],
        ]);
    }
}
