<?php

namespace App\Models;

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
 * @property string $priority
 * @property string $status
 * @property int|null $assigned_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'title', 'description', 'priority', 'assigned_to', 'status'])]

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

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

    protected function scopeByPriority($query, string $priority): void
    {
        $query->where('priority', $priority);
    }

    protected function scopeUnassigned($query): void
    {
        $query->whereNull('assigned_to');
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

    public function priorityLabel(): string
    {
        return match ($this->priority) {
            'low' => __('Baja'),
            'medium' => __('Media'),
            'high' => __('Alta'),
            'urgent' => __('Urgente'),
            default => __('Desconocida'),
        };
    }

    public function priorityIcon(): string
    {
        return match ($this->priority) {
            'low' => svg('hugeicons-signal-low-02', 'text-green-500')->toHtml(),
            'medium' => svg('hugeicons-signal-medium-02', 'text-yellow-500')->toHtml(),
            'high' => svg('hugeicons-signal-full-02', 'text-orange-500')->toHtml(),
            'urgent' => svg('hugeicons-alert-02', 'text-red-500')->toHtml(),
            default => svg('hugeicons-signal-low-02', 'text-gray-500')->toHtml(),
        };
    }
}
