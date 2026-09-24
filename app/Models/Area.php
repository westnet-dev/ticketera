<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Tickets authored by the area's users.
     *
     * An area owns tickets only through its users — `tickets` has no `area_id`
     * of its own, so moving a user to another area moves their tickets with them.
     * Tickets of soft-deleted users drop out on their own, which is what the
     * ticket cap wants: someone who left should not hold their team's quota.
     *
     * @return HasManyThrough<Ticket, User, $this>
     */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, User::class);
    }
}
