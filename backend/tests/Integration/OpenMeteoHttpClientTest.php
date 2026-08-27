<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class OpenMeteoHttpClientTest extends TestCase
{
    public function testSendsAGetRequestAndDecodesAJsonResponse(): void
    {
        $client = (new MockHttpClient(
            static function (string $method, string $url, array $options): MockResponse {
                self::assertSame('GET', $method);
                self::assertSame('https://api.open-meteo.com/v1/forecast?latitude=48.8566&longitude=2.3522&current=temperature_2m', $url);
                self::assertStringContainsString(
                    'application/json',
                    strtolower(implode(' ', $options['normalized_headers']['accept'])),
                );

                return new MockResponse(json_encode([
                    'latitude' => 48.86,
                    'longitude' => 2.35,
                    'current' => ['temperature_2m' => 18.4],
                ], JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            },
            'https://api.open-meteo.com',
        ))->withOptions(['headers' => ['Accept' => 'application/json']]);

        $response = $client->request('GET', '/v1/forecast', [
            'query' => [
                'latitude' => 48.8566,
                'longitude' => 2.3522,
                'current' => 'temperature_2m',
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(18.4, $response->toArray()['current']['temperature_2m']);
    }

    public function testExposesNetworkFailuresAsAControlledTransportException(): void
    {
        $failedResponseBody = static function (): \Generator {
            yield '';

            throw new TransportException('Open-Meteo is unreachable.');
        };

        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            $failedResponseBody(),
        ));

        $this->expectException(TransportExceptionInterface::class);

        $client->request('GET', 'https://api.open-meteo.com/v1/forecast')->getContent();
    }
}
