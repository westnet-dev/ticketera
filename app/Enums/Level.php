<?php

namespace App\Enums;

/**
 * The three-step scale the user rates a ticket's importance, urgency and impact on.
 */
enum Level: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Baja'),
            self::Medium => __('Media'),
            self::High => __('Alta'),
        };
    }
}
