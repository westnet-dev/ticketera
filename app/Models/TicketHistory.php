<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property string $field
 * @property string|null $from_value
 * @property string|null $to_value
 * @property Carbon $created_at
 */
#[Fillable(['ticket_id', 'user_id', 'field', 'from_value', 'to_value'])]
class TicketHistory extends Model
{
    protected $table = 'ticket_history';

    /**
     * This table is append-only: there is no updated_at column to maintain.
     */
    public const UPDATED_AT = null;

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
