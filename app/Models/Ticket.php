<?php

namespace App\Models;

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
 * @property int|null $created_by
 * @property string $title
 * @property string $description
 * @property int $priority
 * @property int $urgency
 * @property int $impact
 * @property string $status
 * @property TriageStatus $triage_status
 * @property ValidationStatus $validation_status
 * @property int|null $resolution_rating
 * @property Carbon|null $validated_at
 * @property int|null $assigned_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'created_by', 'title', 'description', 'priority', 'urgency', 'impact', 'assigned_to', 'status', 'triage_status', 'validation_status', 'resolution_rating', 'validated_at'])]

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['draft', 'open', 'in_progress', 'paused', 'resolved', 'cancelled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triage_status' => TriageStatus::class,
            'validation_status' => ValidationStatus::class,
            'validated_at' => 'datetime',
        ];
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

    protected function scopeByPriority($query, int $priority): void
    {
        $query->where('priority', $priority);
    }

    protected function scopeUnassigned($query): void
    {
        $query->whereNull('assigned_to');
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
        return match ($this->status) {
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
