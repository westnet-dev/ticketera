<?php

use App\Models\TicketSetting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Ticket settings')] class extends Component {
    public int $max_open_tickets_per_user = 5;

    public function mount(): void
    {
        $this->max_open_tickets_per_user = TicketSetting::current()->max_open_tickets_per_user;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'max_open_tickets_per_user' => 'required|integer|min:1',
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
        :subheading="__('Configurá el máximo de tickets sin cerrar que puede tener un cliente a la vez.')"
    >
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input
                wire:model="max_open_tickets_per_user"
                type="number"
                min="1"
                :label="__('Máximo de tickets sin cerrar por cliente')"
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">
                    {{ __('Guardar') }}
                </flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
