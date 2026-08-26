<?php

namespace App\Service;

final class WeatherAdviceService
{
    /**
     * @param array{temperature: float, humidity: int, wind: float, condition: string, freshness?: string} $weather
     */
    public function getAdvice(array $weather): string
    {
        if (str_contains($weather['condition'], 'Orage')) {
            return 'Décalez votre séance extérieure pour votre sécurité.';
        }

        if ($weather['temperature'] > 30) {
            return 'Réduisez l’intensité et buvez davantage.';
        }

        if (str_contains($weather['condition'], 'Pluie')
            || str_contains($weather['condition'], 'Bruine')) {
            return 'Prévoyez des chaussures adaptées et restez prudent sur les appuis.';
        }

        if ($weather['wind'] >= 35) {
            return 'Adaptez votre allure et choisissez si possible un parcours abrité.';
        }

        if ($weather['temperature'] <= 5) {
            return 'Prévoyez plusieurs couches et un échauffement progressif.';
        }

        return 'Les conditions sont adaptées à votre séance prévue.';
    }
}
