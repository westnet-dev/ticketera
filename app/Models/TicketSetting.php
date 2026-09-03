<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $max_open_tickets_per_user
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['max_open_tickets_per_user'])]
class TicketSetting extends Model
{
    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['max_open_tickets_per_user' => 5]);
    }
}
