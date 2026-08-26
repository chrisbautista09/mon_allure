<?php

namespace App\Service;

use App\Exception\WeatherUnavailableException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeatherService
{
    private const int CACHE_TTL_SECONDS = 3600;
    private const int STALE_CACHE_TTL_SECONDS = 10800;

    public function __construct(
        private readonly HttpClientInterface $openMeteoClient,
        private readonly CacheItemPoolInterface $weatherCache,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array{temperature: float, humidity: int, wind: float, condition: string, freshness: 'FRESH'|'STALE'}
     */
    public function getWeather(float $latitude, float $longitude): array
    {
        $this->validateCoordinates($latitude, $longitude);

        $cacheKey = $this->cacheKey($latitude, $longitude);
        $freshItem = $this->weatherCache->getItem($cacheKey);

        if ($freshItem->isHit()) {
            return [...$freshItem->get(), 'freshness' => 'FRESH'];
        }

        try {
            $weather = $this->fetchWeather($latitude, $longitude);
        } catch (WeatherUnavailableException $exception) {
            $staleItem = $this->weatherCache->getItem($cacheKey.'_stale');

            if ($staleItem->isHit()) {
                return [...$staleItem->get(), 'freshness' => 'STALE'];
            }

            throw $exception;
        }

        $freshItem->set($weather)->expiresAt(
            $this->clock->now()->modify(sprintf('+%d seconds', self::CACHE_TTL_SECONDS)),
        );
        $this->weatherCache->save($freshItem);
        $staleItem = $this->weatherCache->getItem($cacheKey.'_stale');
        $staleItem->set($weather)->expiresAt(
            $this->clock->now()->modify(sprintf('+%d seconds', self::STALE_CACHE_TTL_SECONDS)),
        );
        $this->weatherCache->save($staleItem);

        return [...$weather, 'freshness' => 'FRESH'];
    }

    /**
     * @return array{temperature: float, humidity: int, wind: float, condition: string}
     */
    private function fetchWeather(float $latitude, float $longitude): array
    {

        try {
            $data = $this->openMeteoClient->request('GET', '/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code',
                    'temperature_unit' => 'celsius',
                    'wind_speed_unit' => 'kmh',
                    'timezone' => 'auto',
                    'forecast_days' => 1,
                ],
            ])->toArray();
        } catch (TransportExceptionInterface|HttpExceptionInterface|DecodingExceptionInterface $exception) {
            throw new WeatherUnavailableException(
                'Les données météo sont temporairement indisponibles.',
                previous: $exception,
            );
        }

        $current = $data['current'] ?? null;

        if (!is_array($current)
            || !is_numeric($current['temperature_2m'] ?? null)
            || !is_numeric($current['relative_humidity_2m'] ?? null)
            || !is_numeric($current['wind_speed_10m'] ?? null)
            || !is_numeric($current['weather_code'] ?? null)
        ) {
            throw new WeatherUnavailableException('La réponse météo reçue est incomplète.');
        }

        return [
            'temperature' => (float) $current['temperature_2m'],
            'humidity' => (int) round((float) $current['relative_humidity_2m']),
            'wind' => (float) $current['wind_speed_10m'],
            'condition' => $this->conditionFromCode((int) $current['weather_code']),
        ];
    }

    private function cacheKey(float $latitude, float $longitude): string
    {
        $position = sprintf('%.2F,%.2F', $latitude, $longitude);

        return 'weather_'.hash('sha256', $position);
    }

    private function validateCoordinates(float $latitude, float $longitude): void
    {
        if (!is_finite($latitude) || $latitude < -90 || $latitude > 90) {
            throw new \InvalidArgumentException('La latitude doit être comprise entre -90 et 90.');
        }

        if (!is_finite($longitude) || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('La longitude doit être comprise entre -180 et 180.');
        }
    }

    private function conditionFromCode(int $code): string
    {
        return match (true) {
            $code === 0 => 'Ciel dégagé',
            in_array($code, [1, 2], true) => 'Partiellement nuageux',
            $code === 3 => 'Nuageux',
            in_array($code, [45, 48], true) => 'Brouillard',
            in_array($code, [51, 53, 55, 56, 57], true) => 'Bruine',
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => 'Pluie',
            in_array($code, [71, 73, 75, 77, 85, 86], true) => 'Neige',
            in_array($code, [95, 96, 99], true) => 'Orage',
            default => 'Condition inconnue',
        };
    }
}
