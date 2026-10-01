<?php

namespace App\Enums;

/**
 * A ticket's priority, derived from its importance and urgency rather than picked by the user.
 */
enum TicketPriority: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;

    /**
     * Read the priority off the importance × urgency matrix.
     *
     * Spelled out cell by cell so it reads like the matrix agreed with the
     * business: no arithmetic on the levels reproduces it without special cases.
     */
    public static function fromMatrix(Level $importance, Level $urgency): self
    {
        return match ([$importance, $urgency]) {
            [Level::High, Level::High] => self::Critical,
            [Level::High, Level::Medium] => self::High,
            [Level::High, Level::Low] => self::Medium,
            [Level::Medium, Level::High] => self::High,
            [Level::Medium, Level::Medium] => self::Medium,
            [Level::Medium, Level::Low] => self::Low,
            [Level::Low, Level::High] => self::Medium,
            [Level::Low, Level::Medium] => self::Low,
            [Level::Low, Level::Low] => self::Low,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Baja'),
            self::Medium => __('Media'),
            self::High => __('Alta'),
            self::Critical => __('Crítica'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'amber',
            self::High => 'orange',
            self::Critical => 'red',
        };
    }
}
