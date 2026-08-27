<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class WeatherTemplateTest extends KernelTestCase
{
    public function testDisplaysAccessibleResponsiveWeatherCardAndAttribution(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        $html = $twig->render('dashboard/_weather.html.twig', [
            'location' => 'Toulouse, France',
        ]);

        self::assertStringContainsString('data-testid="weather-card"', $html);
        self::assertStringContainsString('data-weather-url="/api/dashboard/weather"', $html);
        self::assertStringContainsString('data-weather-location="Toulouse, France"', $html);
        self::assertStringContainsString('aria-live="polite"', $html);
        self::assertStringContainsString('Conditions actuelles', $html);
        self::assertStringContainsString('Vent', $html);
        self::assertStringContainsString('Conseil running', $html);
        self::assertStringContainsString('sm:grid-cols-2', $html);
        self::assertStringContainsString('https://open-meteo.com/', $html);
        self::assertStringContainsString('Réessayer la localisation', $html);
    }
}
