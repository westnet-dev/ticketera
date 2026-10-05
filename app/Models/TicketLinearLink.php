<?php

namespace App\Models;

use App\Enums\LinearLinkSource;
use Database\Factories\TicketLinearLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Linear issue related to a ticket, with the issue's fields cached so listings
 * can show them without calling Linear once per row.
 *
 * @property int $id
 * @property int $ticket_id
 * @property string $linear_issue_id
 * @property string $identifier
 * @property string $title
 * @property string $url
 * @property string $state_name
 * @property string $state_type
 * @property string|null $assignee_name
 * @property LinearLinkSource $source
 * @property int|null $linked_by
 * @property Carbon $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['linear_issue_id', 'identifier', 'title', 'url', 'state_name', 'state_type', 'assignee_name', 'source', 'linked_by', 'synced_at'])]
class TicketLinearLink extends Model
{
    /** @use HasFactory<TicketLinearLinkFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => LinearLinkSource::class,
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function wasDetectedFromLinear(): bool
    {
        return $this->source === LinearLinkSource::Attachment;
    }

    /**
     * Badge color for the issue's Linear state type, matching the ticket status palette.
     */
    public function stateColor(): string
    {
        return match ($this->state_type) {
            'started' => 'yellow',
            'completed' => 'blue',
            'canceled' => 'red',
            default => 'zinc',
        };
    }
}
