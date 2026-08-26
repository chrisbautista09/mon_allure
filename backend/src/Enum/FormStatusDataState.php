<?php

namespace App\Enum;

enum FormStatusDataState: string
{
    case NO_ACTIVE_PLAN = 'NO_ACTIVE_PLAN';
    case NO_PERFORMANCE = 'NO_PERFORMANCE';
    case LIMITED_DATA = 'LIMITED_DATA';
    case READY = 'READY';
    case PLAN_COMPLETED = 'PLAN_COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::NO_ACTIVE_PLAN, self::NO_PERFORMANCE => 'État de forme indisponible',
            self::LIMITED_DATA => 'Analyse en cours',
            self::READY => 'Analyse disponible',
            self::PLAN_COMPLETED => 'Plan terminé',
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::NO_ACTIVE_PLAN => 'Aucun plan actif. Définissez un objectif pour commencer.',
            self::NO_PERFORMANCE => 'Réalisez vos premières séances pour calculer votre état de forme.',
            self::LIMITED_DATA => 'Les premières données sont disponibles. La précision augmentera après six performances.',
            self::READY => 'Votre état de forme s’appuie sur suffisamment de performances récentes.',
            self::PLAN_COMPLETED => 'Dernier niveau atteint à la fin de votre plan.',
        };
    }
}
