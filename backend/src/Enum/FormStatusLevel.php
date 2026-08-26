<?php

namespace App\Enum;

enum FormStatusLevel: string
{
    case LOW = 'LOW';
    case FAIR = 'FAIR';
    case GOOD = 'GOOD';
    case EXCELLENT = 'EXCELLENT';

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score < 40 => self::LOW,
            $score < 70 => self::FAIR,
            $score < 90 => self::GOOD,
            default => self::EXCELLENT,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LOW => 'Forme faible',
            self::FAIR => 'Forme correcte',
            self::GOOD => 'Bonne forme',
            self::EXCELLENT => 'Très bonne forme',
        };
    }
}
