<?php

namespace App\Enum;

enum SessionStatus: string
{
    case PLANNED = 'planned';
    case DONE = 'done';
    case PARTIALLY_DONE = 'partially_done';
    case MISSED = 'missed';

    public function isTerminal(): bool
    {
        return $this !== self::PLANNED;
    }

    public function isSuccessful(): bool
    {
        return $this === self::DONE;
    }

    public function isFailed(): bool
    {
        return in_array($this, [self::PARTIALLY_DONE, self::MISSED], true);
    }
}
