<x-layouts::app :title="$ticket->title">
    <div class="flex h-full flex-col gap-3 lg:grid lg:grid-cols-5">
        <div class="contents lg:col-span-4 lg:flex lg:h-full lg:w-full lg:flex-1 lg:flex-col lg:gap-4 lg:rounded-xl">
            <div class="order-1 flex min-w-0 flex-col items-start justify-between gap-3">
                <div class="flex w-full flex-wrap items-center justify-between gap-2">
                    <div class="flex min-w-0 items-center gap-2">
                        <flux:heading size="xl" class="wrap-break-word">{{ $ticket->title }}</flux:heading>
                    </div>

                    <flux:button
                        href="{{ route('ticket.index') }}"
                        wire:navigate
                        variant="outline"
                        size="sm"
                        icon="arrow-left"
                    >
                        {{ __("Volver a mis tickets") }}
                    </flux:button>
                </div>
                <p class="mb-3 w-full wrap-break-word text-base text-neutral-600 dark:text-neutral-300">
                    {{ $ticket->description }}
                </p>
                @if ($ticket->images->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($ticket->images as $image)
                            <flux:modal.trigger name="ticket-image-{{ $image->id }}">
                                <button type="button">
                                    <img
                                        src="{{ $image->getImageUrlAttribute() }}"
                                        alt="{{ $ticket->title }}"
                                        class="h-20 w-20 rounded-lg object-cover"
                                    />
                                </button>
                            </flux:modal.trigger>

                            <flux:modal name="ticket-image-{{ $image->id }}" class="max-w-3xl">
                                <img
                                    src="{{ $image->getImageUrlAttribute() }}"
                                    alt="{{ $ticket->title }}"
                                    class="max-h-[80vh] w-full rounded-lg object-contain"
                                />
                            </flux:modal>
                        @endforeach
                    </div>
                @endif

                @can('validateResolution', $ticket)
                    <div class="w-full rounded-xl border border-yellow-500/50 bg-yellow-500/10 p-4">
                        <flux:heading size="sm">{{ __('Este ticket está esperando tu validación') }}</flux:heading>
                        <flux:subheading>
                            {{ __('El equipo marcó el ticket como resuelto. Confirmá si el pedido quedó resuelto y calificá la solución, o indicá qué quedó pendiente.') }}
                        </flux:subheading>
                    </div>
                @endcan

                @can('reviseTriage', $ticket)
                    <div class="w-full">
                        <livewire:tickets.revise-ticket :ticket="$ticket" />
                    </div>
                @endcan
            </div>

            <div class="order-3 flex min-w-0 flex-1 flex-col">
                <livewire:tickets.ticket-chat :ticket="$ticket" />
            </div>
        </div>
        <div class="contents lg:col-span-1 lg:flex lg:flex-col lg:gap-1 lg:rounded-xl lg:bg-neutral-300 lg:p-4 lg:dark:bg-neutral-700">
            <div class="order-2 flex flex-col gap-1 rounded-xl bg-neutral-300 p-4 lg:rounded-none lg:bg-transparent lg:p-0 dark:bg-neutral-700 lg:dark:bg-transparent">
                <p class="font-semibold mb-4">Propiedades</p>
                <p>Creación: {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
                @if ($ticket->wasCreatedOnBehalf())
                    <p>
                        {{ __('Creado por :author en nombre de :owner', [
                            'author' => $ticket->createdBy?->name ?? __('un usuario eliminado'),
                            'owner' => $ticket->user->name,
                        ]) }}
                    </p>
                @endif
                <p>Actualización: {{ $ticket->updated_at->format('d/m/Y H:i') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    Estado:
                    @can('changeStatus', $ticket)
                        <livewire:tickets.ticket-status-selector :ticket="$ticket" />
                    @else
                        <flux:badge
                            size="sm"
                            :color="$ticket->statusColor()"
                        >
                            {{ $ticket->statusLabel() }}
                        </flux:badge>
                    @endcan
                    @unless ($ticket->isTriageApproved())
                        <flux:badge size="sm" :color="$ticket->triageStatusColor()">
                            {{ $ticket->triageStatusLabel() }}
                        </flux:badge>
                    @endunless
                </div>

                @if ($ticket->validationWasRequested())
                    <div class="flex flex-wrap items-center gap-2">
                        {{ __('Validación') }}:
                        <flux:badge size="sm" :color="$ticket->validationStatusColor()">
                            {{ $ticket->validationStatusLabel() }}
                        </flux:badge>
                    </div>
                @endunless

                @if ($ticket->resolution_rating !== null)
                    <div class="flex items-center gap-2">
                        {{ __('Calificación') }}:
                        <span class="flex items-center gap-0.5 text-yellow-500">
                            @for ($star = 1; $star <= 5; $star++)
                                <flux:icon.star
                                    variant="{{ $star <= $ticket->resolution_rating ? 'solid' : 'outline' }}"
                                    class="size-4"
                                />
                            @endfor
                        </span>
                    </div>
                @endif

                @can('approve', $ticket)
                    <p class="mt-6 mb-4 font-semibold">Acciones</p>
                    <livewire:tickets.ticket-triage-actions :ticket="$ticket" />
                @endcan

                @can('validateResolution', $ticket)
                    <p class="mt-6 mb-4 font-semibold">Acciones</p>
                    <livewire:tickets.ticket-validation-actions :ticket="$ticket" />
                @endcan
            </div>

            <div class="order-4 rounded-xl bg-neutral-300 p-4 lg:mt-6 lg:rounded-none lg:bg-transparent lg:p-0 dark:bg-neutral-700 lg:dark:bg-transparent">
                <livewire:tickets.ticket-history-timeline :ticket="$ticket" />
            </div>
        </div>
    </div>
</x-layouts::app>
