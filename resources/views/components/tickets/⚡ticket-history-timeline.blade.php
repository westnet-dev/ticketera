<?php

use App\Enums\TriageStatus;
use App\Enums\ValidationStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public function with(): array
    {
        return [
            'entries' => $this->ticket->history()
                ->with('changedBy')
                ->unless(auth()->user()?->isAdmin(), fn ($query) => $query->whereNotIn('field', TicketHistory::ADMIN_ONLY_FIELDS))
                ->get(),
        ];
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'status' => __('Estado'),
            'triage_status' => __('Triage'),
            'validation_status' => __('Validación'),
            'assigned_to' => __('Asignación'),
            'difficulty' => __('Dificultad'),
            'details' => __('Detalles editados'),
            default => $field,
        };
    }

    public function valueLabel(string $field, ?string $value): string
    {
        return match ($field) {
            'status' => $value !== null ? Ticket::labelForStatus($value) : __('Sin estado'),
            'triage_status' => $value !== null ? Ticket::labelForTriageStatus(TriageStatus::from($value)) : __('Sin triage'),
            'validation_status' => $value !== null ? Ticket::labelForValidationStatus(ValidationStatus::from($value)) : __('Sin validación'),
            'assigned_to' => $this->userLabel($value),
            'difficulty' => $value ?? __('Sin estimar'),
            default => $value ?? '—',
        };
    }

    /**
     * Human-readable list of the fields changed by a details edit.
     */
    public function editedFieldsLabel(?string $fields): string
    {
        return collect(explode(',', (string) $fields))
            ->filter()
            ->map(fn (string $field) => match ($field) {
                'title' => __('Título'),
                'description' => __('Descripción'),
                'importance' => __('Importancia'),
                'priority' => __('Prioridad'),
                'urgency' => __('Urgencia'),
                'impact' => __('Impacto'),
                'category_id' => __('Categoría'),
                'images' => __('Imágenes'),
                default => $field,
            })
            ->implode(', ');
    }

    private function userLabel(?string $userId): string
    {
        if ($userId === null) {
            return __('Sin asignar');
        }

        return User::withTrashed()->find($userId)?->name ?? __('Usuario eliminado');
    }
};
?>

<div class="flex flex-col gap-3">
    <flux:heading size="sm">{{ __('Historial') }}</flux:heading>

    @forelse ($entries as $entry)
        <div class="flex flex-col gap-1 border-l-2 border-neutral-200 pl-3 text-sm dark:border-neutral-500">
            <p>
                <span class="font-medium">{{ $this->fieldLabel($entry->field) }}:</span>
                @if ($entry->field === 'details')
                    <span>{{ $this->editedFieldsLabel($entry->to_value) }}</span>
                @else
                    <span>{{ $this->valueLabel($entry->field, $entry->from_value) }}</span>
                    <flux:icon.arrow-right class="h-3 w-3 inline" />
                    <span>{{ $this->valueLabel($entry->field, $entry->to_value) }}</span>
                @endif
            </p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                {{ $entry->changedBy?->name ?? __('Sistema') }} · {{ $entry->created_at->diffForHumans() }}
            </p>
        </div>
    @empty
        <p class="text-sm text-neutral-500">{{ __('Todavía no hay cambios registrados para este ticket.') }}</p>
    @endforelse
</div>
