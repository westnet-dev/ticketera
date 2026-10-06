<?php

namespace App\Enums;

/**
 * The admin-only effort estimate of a ticket, on the Fibonacci scale.
 */
enum Difficulty: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Five = 5;
    case Eight = 8;
    case Thirteen = 13;

    public function label(): string
    {
        return (string) $this->value;
    }
}
