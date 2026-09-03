<?php

namespace App\Models;

use App\Enums\TriageStatus;
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
 * @property string $title
 * @property string $description
 * @property int $priority
 * @property int $urgency
 * @property int $impact
 * @property string $status
 * @property TriageStatus $triage_status
 * @property int|null $assigned_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'title', 'description', 'priority', 'urgency', 'impact', 'assigned_to', 'status', 'triage_status'])]

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'triage_status' => TriageStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(TicketImage::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('created_at');
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

    protected function scopeClosed($query): void
    {
        $query->where('status', 'closed');
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

    public function statusColor(): string
    {
        return match ($this->status) {
            'open' => 'green',
            'in_progress' => 'yellow',
            'resolved' => 'blue',
            'closed' => 'red',
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
            'open' => __('Abierto'),
            'in_progress' => __('En Progreso'),
            'resolved' => __('Resuelto'),
            'closed' => __('Cerrado'),
            default => __('Desconocido'),
        };
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
        return match ($this->triage_status) {
            TriageStatus::Pending => __('Pendiente de triage'),
            TriageStatus::Approved => __('Aprobado'),
            TriageStatus::Rejected => __('Rechazado'),
        };
    }
}
