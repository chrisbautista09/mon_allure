<?php

namespace App\Enum;

enum AdaptationDecision: string
{
    case INCREASE = 'INCREASE_LOAD';
    case MAINTAIN = 'MAINTAIN_LOAD';
    case REDUCE = 'REDUCE_LOAD';
}
