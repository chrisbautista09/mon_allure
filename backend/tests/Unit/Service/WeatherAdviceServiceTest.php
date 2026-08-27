<?php

namespace App\Tests\Unit\Service;

use App\Service\WeatherAdviceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherAdviceServiceTest extends TestCase
{
    /**
     * @param array{temperature: float, humidity: int, wind: float, condition: string} $weather
     */
    #[DataProvider('weatherProvider')]
    public function testReturnsSimpleTrainingAdvice(array $weather, string $expectedAdvice): void
    {
        self::assertSame($expectedAdvice, (new WeatherAdviceService())->getAdvice($weather));
    }

    /** @return iterable<string, array{array{temperature: float, humidity: int, wind: float, condition: string}, string}> */
    public static function weatherProvider(): iterable
    {
        yield 'thunderstorm has safety priority' => [
            self::weather(32, 10, 'Orage'),
            'Décalez votre séance extérieure pour votre sécurité.',
        ];
        yield 'temperature above thirty degrees' => [
            self::weather(31, 10, 'Ciel dégagé'),
            'Réduisez l’intensité et buvez davantage.',
        ];
        yield 'thirty degrees remains below heat threshold' => [
            self::weather(30, 10, 'Ciel dégagé'),
            'Les conditions sont adaptées à votre séance prévue.',
        ];
        yield 'rain' => [
            self::weather(15, 10, 'Pluie'),
            'Prévoyez des chaussures adaptées et restez prudent sur les appuis.',
        ];
        yield 'drizzle' => [
            self::weather(15, 10, 'Bruine'),
            'Prévoyez des chaussures adaptées et restez prudent sur les appuis.',
        ];
        yield 'strong wind' => [
            self::weather(15, 35, 'Nuageux'),
            'Adaptez votre allure et choisissez si possible un parcours abrité.',
        ];
        yield 'cold weather' => [
            self::weather(5, 10, 'Ciel dégagé'),
            'Prévoyez plusieurs couches et un échauffement progressif.',
        ];
    }

    /** @return array{temperature: float, humidity: int, wind: float, condition: string} */
    private static function weather(float $temperature, float $wind, string $condition): array
    {
        return [
            'temperature' => $temperature,
            'humidity' => 60,
            'wind' => $wind,
            'condition' => $condition,
        ];
    }
}
