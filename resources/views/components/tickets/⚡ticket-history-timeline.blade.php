<?php

use App\Enums\TriageStatus;
use App\Enums\ValidationStatus;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public Ticket $ticket;

    public function with(): array
    {
        return [
            'entries' => $this->ticket->history()->with('changedBy')->get(),
        ];
    }

    public function fieldLabel(string $field): string
    {
        return match ($field) {
            'status' => __('Estado'),
            'triage_status' => __('Triage'),
            'validation_status' => __('Validación'),
            'assigned_to' => __('Asignación'),
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
            default => $value ?? '—',
        };
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
    <p class="font-semibold">{{ __('Historial') }}</p>

    @forelse ($entries as $entry)
        <div class="flex flex-col gap-1 border-l-2 border-neutral-400 pl-3 text-sm dark:border-neutral-500">
            <p>
                <span class="font-medium">{{ $this->fieldLabel($entry->field) }}:</span>
                <span>{{ $this->valueLabel($entry->field, $entry->from_value) }}</span>
                <flux:icon.arrow-right class="h-3 w-3 inline" />
                <span>{{ $this->valueLabel($entry->field, $entry->to_value) }}</span>
            </p>
            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                {{ $entry->changedBy?->name ?? __('Sistema') }} · {{ $entry->created_at->diffForHumans() }}
            </p>
        </div>
    @empty
        <p class="text-sm text-neutral-500">{{ __('Todavía no hay cambios registrados para este ticket.') }}</p>
    @endforelse
</div>
