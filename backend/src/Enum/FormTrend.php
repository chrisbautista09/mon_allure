<?php

namespace App\Enum;

enum FormTrend: string
{
    case IMPROVING = 'IMPROVING';
    case STABLE = 'STABLE';
    case DECLINING = 'DECLINING';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::IMPROVING => 'progression',
            self::STABLE => 'stable',
            self::DECLINING => 'baisse',
            self::UNKNOWN => 'données insuffisantes',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::IMPROVING => 'Votre forme progresse.',
            self::STABLE => 'Votre forme est stable.',
            self::DECLINING => 'Votre charge semble élevée.',
            self::UNKNOWN => 'Effectuez davantage de séances pour afficher une tendance.',
        };
    }
}
