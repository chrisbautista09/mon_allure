<?php

namespace App\Enum;

enum SessionStatus: string
{
    case PLANNED = 'planned';
    case COMPLETED = 'completed';
    case MISSED = 'missed';
    case CANCELLED = 'cancelled';

    public function isTerminal(): bool
    {
        return $this !== self::PLANNED;
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::MISSED, self::CANCELLED], true);
    }
}
