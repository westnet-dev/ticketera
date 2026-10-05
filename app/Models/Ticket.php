<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\Level;
use App\Enums\TicketPriority;
use App\Enums\TriageStatus;
use App\Enums\ValidationStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $area_id
 * @property Area|null $area
 * @property int|null $created_by
 * @property string $title
 * @property string|null $description
 * @property Level $importance
 * @property Level $urgency
 * @property Level $impact
 * @property TicketPriority $priority
 * @property int|null $category_id
 * @property TicketCategory|null $category
 * @property string $status
 * @property TriageStatus $triage_status
 * @property ValidationStatus $validation_status
 * @property int|null $resolution_rating
 * @property Carbon|null $validated_at
 * @property int|null $assigned_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'area_id', 'created_by', 'title', 'description', 'importance', 'urgency', 'impact', 'category_id', 'assigned_to', 'status', 'triage_status', 'validation_status', 'resolution_rating', 'validated_at'])]

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['draft', 'open', 'in_progress', 'paused', 'resolved', 'cancelled'];

    /**
     * The order statuses are presented in when a listing is sorted by status.
     *
     * This is a display order, not a state machine: nothing stops a ticket from
     * moving between any two statuses. It exists because the stored values are
     * plain strings, so sorting by the column alone gives alphabetical order
     * (cancelled, draft, in_progress, ...), which reads as noise to a human.
     *
     * @var array<int, string>
     */
    public const STATUS_FLOW = ['open', 'in_progress', 'paused', 'resolved', 'cancelled', 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description' => SanitizedHtml::class,
            'importance' => Level::class,
            'urgency' => Level::class,
            'impact' => Level::class,
            'priority' => TicketPriority::class,
            'triage_status' => TriageStatus::class,
            'validation_status' => ValidationStatus::class,
            'validated_at' => 'datetime',
        ];
    }

    /**
     * Priority is never picked by hand: it is read off the importance × urgency
     * matrix on every save, so no form can forget it or override it.
     */
    protected static function booted(): void
    {
        static::saving(function (Ticket $ticket): void {
            $ticket->priority = TicketPriority::fromMatrix(
                $ticket->importance ?? Level::Medium,
                $ticket->urgency ?? Level::Medium,
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user who actually filed the ticket, which may differ from its author.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The area the ticket was filed for, picked among its author's areas.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TicketImage::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
    }

    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderBy('created_at');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @param  Builder  $query
     */
    protected function scopeForUsers($query, array $userIds): void
    {
        $query->whereIn('user_id', $userIds);
    }

    /**
     * Tickets shown in a user's own listings: the ones they authored and,
     * for admins, also the ones assigned to them.
     *
     * @param  Builder  $query
     */
    protected function scopeListedFor($query, User $user): void
    {
        $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id);

            if ($user->isAdmin()) {
                $q->orWhere('assigned_to', $user->id);
            }
        });
    }

    /**
     * Tickets that are being worked on, which is what the "en curso" tab lists.
     */
    protected function scopeOngoing($query): void
    {
        $query->whereIn('status', ['open', 'in_progress', 'paused']);
    }

    protected function scopeOpen($query): void
    {
        $query->where('status', 'open');
    }

    protected function scopeResolved($query): void
    {
        $query->where('status', 'resolved');
    }

    protected function scopeDraft($query): void
    {
        $query->where('status', 'draft');
    }

    protected function scopePaused($query): void
    {
        $query->where('status', 'paused');
    }

    protected function scopeCancelled($query): void
    {
        $query->where('status', 'cancelled');
    }

    protected function scopeFinished($query): void
    {
        $query->whereIn('status', ['resolved', 'cancelled']);
    }

    /**
     * Tickets that still represent pending work, which is what the ticket cap counts.
     */
    protected function scopeUnclosed($query): void
    {
        $query->whereNotIn('status', ['resolved', 'cancelled', 'draft']);
    }

    protected function scopeByPriority($query, TicketPriority $priority): void
    {
        $query->where('priority', $priority);
    }

    /**
     * Order by priority, breaking ties within a priority level by impact.
     *
     * @param  Builder  $query
     */
    protected function scopeOrderByPriority($query, string $direction = 'desc'): void
    {
        $query->orderBy('priority', $direction)->orderBy('impact', $direction);
    }

    /**
     * Order by status following STATUS_FLOW rather than the alphabetical order
     * of the stored string.
     *
     * Spelled out as a CASE instead of MySQL's FIELD() so the same query runs
     * on SQLite. The statuses travel as bindings and the direction is resolved
     * against a whitelist, so nothing from the request reaches the SQL.
     *
     * @param  Builder<Ticket>  $query
     */
    protected function scopeOrderByStatusFlow($query, string $direction = 'asc'): void
    {
        $direction = static::sortDirection($direction);

        $cases = [];
        $bindings = [];

        foreach (self::STATUS_FLOW as $position => $status) {
            $cases[] = 'WHEN tickets.status = ? THEN '.$position;
            $bindings[] = $status;
        }

        $query->orderByRaw(
            'CASE '.implode(' ', $cases).' ELSE '.count(self::STATUS_FLOW).' END '.$direction,
            $bindings,
        );
    }

    /**
     * Order by category name, keeping uncategorised tickets last in both
     * directions rather than letting the driver decide where NULLs land.
     *
     * The explicit select is load-bearing: without it the join overwrites
     * tickets.id with ticket_categories.id and the listing renders the wrong IDs.
     *
     * @param  Builder<Ticket>  $query
     */
    protected function scopeOrderByCategoryName($query, string $direction = 'asc'): void
    {
        $direction = static::sortDirection($direction);

        $query->select('tickets.*')
            ->leftJoin('ticket_categories', 'ticket_categories.id', '=', 'tickets.category_id')
            ->orderByRaw('tickets.category_id IS NULL')
            ->orderBy('ticket_categories.name', $direction);
    }

    /**
     * Resolve a sort direction against the only two values allowed in SQL.
     *
     * @return 'asc'|'desc'
     */
    public static function sortDirection(?string $direction): string
    {
        return strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';
    }

    protected function scopeUnassigned($query): void
    {
        $query->whereNull('assigned_to');
    }

    /**
     * Narrow a listing by a free-text term.
     *
     * A term that reads as a ticket number (123, TK-123, #TK-123) looks the
     * ticket up by ID; anything else matches the title. Deliberately one path
     * per term and never an orWhere: an orWhere here would escape the filters
     * already on the query and widen the result past what the caller allowed.
     *
     * @param  Builder<Ticket>  $query
     */
    protected function scopeSearch($query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        if (preg_match('/^#?(?:TK-)?(\d+)$/i', $term, $matches) === 1) {
            $query->where('tickets.id', (int) $matches[1]);

            return;
        }

        $query->where('tickets.title', 'like', '%'.$term.'%');
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    protected function scopeForClient($query, ?string $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $query->where('tickets.user_id', $userId);
    }

    /**
     * Narrow a listing by who it is assigned to, where the 'unassigned'
     * sentinel means tickets with nobody on them.
     *
     * @param  Builder<Ticket>  $query
     */
    protected function scopeAssignedToUser($query, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if ($value === 'unassigned') {
            $query->whereNull('tickets.assigned_to');

            return;
        }

        $query->where('tickets.assigned_to', $value);
    }

    protected function scopeApproved($query): void
    {
        $query->where('triage_status', TriageStatus::Approved);
    }

    protected function scopePendingValidation($query): void
    {
        $query->where('validation_status', ValidationStatus::Pending);
    }

    protected function scopeValidated($query): void
    {
        $query->where('validation_status', ValidationStatus::Confirmed);
    }

    public function statusColor(): string
    {
        return static::colorForStatus($this->status);
    }

    public static function colorForStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'zinc',
            'open' => 'green',
            'in_progress' => 'yellow',
            'paused' => 'orange',
            'resolved' => 'blue',
            'cancelled' => 'red',
            default => 'zinc',
        };
    }

    public function statusLabel(): string
    {
        return static::labelForStatus($this->status);
    }

    public static function labelForStatus(string $status): string
    {
        return match ($status) {
            'draft' => __('Borrador'),
            'open' => __('Abierto'),
            'in_progress' => __('En Progreso'),
            'paused' => __('Pausado'),
            'resolved' => __('Resuelto'),
            'cancelled' => __('Cancelado'),
            default => __('Desconocido'),
        };
    }

    public function priorityLabel(): string
    {
        return $this->priority->label();
    }

    public function priorityColor(): string
    {
        return $this->priority->color();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Whether someone other than the ticket's author filed it on their behalf.
     */
    public function wasCreatedOnBehalf(): bool
    {
        return $this->created_by !== null && $this->created_by !== $this->user_id;
    }

    public function isTriageApproved(): bool
    {
        return $this->triage_status === TriageStatus::Approved;
    }

    public function isTriageRejected(): bool
    {
        return $this->triage_status === TriageStatus::Rejected;
    }

    public function triageStatusColor(): string
    {
        return match ($this->triage_status) {
            TriageStatus::Pending => 'yellow',
            TriageStatus::Approved => 'green',
            TriageStatus::Rejected => 'red',
        };
    }

    public function triageStatusLabel(): string
    {
        return static::labelForTriageStatus($this->triage_status);
    }

    public static function labelForTriageStatus(TriageStatus $status): string
    {
        return match ($status) {
            TriageStatus::Pending => __('Pendiente de triage'),
            TriageStatus::Approved => __('Aprobado'),
            TriageStatus::Rejected => __('Rechazado'),
        };
    }

    /**
     * Whether the ticket's author still has to validate the resolution.
     */
    public function awaitsValidation(): bool
    {
        return $this->validation_status === ValidationStatus::Pending;
    }

    /**
     * Whether the ticket ever entered the validation cycle, either still
     * awaiting an answer or already decided by its author.
     */
    public function validationWasRequested(): bool
    {
        return $this->validation_status !== ValidationStatus::NotRequested;
    }

    public function validationStatusColor(): string
    {
        return match ($this->validation_status) {
            ValidationStatus::NotRequested => 'zinc',
            ValidationStatus::Pending => 'yellow',
            ValidationStatus::Confirmed => 'green',
            ValidationStatus::Rejected => 'red',
        };
    }

    public function validationStatusLabel(): string
    {
        return static::labelForValidationStatus($this->validation_status);
    }

    public static function labelForValidationStatus(ValidationStatus $status): string
    {
        return match ($status) {
            ValidationStatus::NotRequested => __('Sin validación'),
            ValidationStatus::Pending => __('Pendiente de validación'),
            ValidationStatus::Confirmed => __('Validado'),
            ValidationStatus::Rejected => __('Validación rechazada'),
        };
    }
}
