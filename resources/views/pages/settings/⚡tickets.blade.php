<?php

use App\Models\TicketSetting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ticket settings')] class extends Component {
    public int $max_open_tickets_per_area = 5;

    public function mount(): void
    {
        $this->max_open_tickets_per_area = TicketSetting::current()->max_open_tickets_per_area;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'max_open_tickets_per_area' => 'required|integer|min:1',
        ]);

        TicketSetting::current()->update($validated);

        Flux::toast(variant: 'success', text: __('Configuración actualizada.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Ticket settings') }}</flux:heading>

    <x-pages::settings.layout
        :heading="__('Tickets')"
        :subheading="__('Configurá el máximo de tickets sin cerrar que puede acumular un área a la vez. El mismo valor rige para todas las áreas, sin importar cuánta gente tenga cada una.')"
    >
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="max_open_tickets_per_area"
                type="number"
                min="1"
                :label="__('Máximo de tickets sin cerrar por área')"
                :description="__('A un cliente sin área asignada se le aplica este mismo número, contado sobre sus propios tickets.')"
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
