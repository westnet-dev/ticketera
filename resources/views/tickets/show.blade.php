<x-layouts::app :title="$ticket->title">
    <div class="h-full grid grid-cols-5 gap-3">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl col-span-4">
            <div class="flex flex-col items-start justify-between gap-3">
                <div class="flex items-center justify-between gap-2 w-full">
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl">{{ $ticket->title }}</flux:heading>
                    </div>

                    <flux:button
                        href="{{ route('ticket.index') }}"
                        wire:navigate
                        variant="ghost"
                        size="sm"
                        icon="arrow-left"
                    >
                        {{ __("Volver a mis tickets") }}
                    </flux:button>
                </div>
                <p class="text-base text-neutral-600 dark:text-neutral-300 mb-3">
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
                <div class="flex items-center gap-2">

                </div>
            </div>

            @can('validateResolution', $ticket)
                <div class="rounded-xl border border-yellow-500/50 bg-yellow-500/10 p-4">
                    <flux:heading size="sm">{{ __('Este ticket está esperando tu validación') }}</flux:heading>
                    <flux:subheading>
                        {{ __('El equipo marcó el ticket como resuelto. Confirmá si el pedido quedó resuelto y calificá la solución, o indicá qué quedó pendiente.') }}
                    </flux:subheading>
                </div>
            @endcan

            @can('reviseTriage', $ticket)
                <livewire:tickets.revise-ticket :ticket="$ticket" />
            @endcan

            <livewire:tickets.ticket-chat :ticket="$ticket" />
        </div>
        <div class="col-span-1 overflow- flex flex-col gap-1 bg-neutral-300 dark:bg-neutral-700 p-4 rounded-xl">
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
            <div class="flex items-center gap-2">
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
                <div class="flex items-center gap-2">
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

            <div class="mt-6">
                <livewire:tickets.ticket-history-timeline :ticket="$ticket" />
            </div>
        </div>
    </div>
</x-layouts::app>
