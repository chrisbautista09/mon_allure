<?php

namespace App\Enum;

enum PerformanceEvaluationResult: string
{
    case OK = 'PERFORMANCE_OK';
    case SUPERIOR = 'PERFORMANCE_SUPERIEURE';
    case INSUFFICIENT = 'PERFORMANCE_INSUFFISANTE';
}
