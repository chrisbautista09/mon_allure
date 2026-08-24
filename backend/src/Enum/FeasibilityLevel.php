<?php

namespace App\Enum;

enum FeasibilityLevel: string
{
    case LOW = 'FAIBLE';
    case MEDIUM = 'MOYEN';
    case GOOD = 'BON';
    case OPTIMAL = 'OPTIMAL';
}
