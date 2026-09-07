<x-layouts::app :title="$ticket->title">
    <div class="h-full grid grid-cols-5">
        <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl col-span-4">
            <div class="flex flex-col items-start justify-between gap-3">
                <div class="flex items-center justify-between gap-2 w-full p-4">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg">{{ $ticket->title }}</flux:heading>
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
                <p class="text-base text-neutral-600 dark:text-neutral-300 mb-3 mx-4">
                    {{ $ticket->description }}
                </p>
                @if ($ticket->images->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mx-4">
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

            @can('reviseTriage', $ticket)
                <livewire:tickets.revise-ticket :ticket="$ticket" />
            @endcan

            <livewire:tickets.ticket-chat :ticket="$ticket" />
        </div>
        <div class="col-span-1 ml-4 flex flex-col gap-2 bg-neutral-700 p-4 rounded-xl">
            <p class="font-semibold mb-4">Propiedades</p>
            <p>Creación: {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
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

            @can('approve', $ticket)
                <p class="mt-6 mb-4 font-semibold">Acciones</p>
                <livewire:tickets.ticket-triage-actions :ticket="$ticket" />
            @endcan

        </div>
    </div>
</x-layouts::app>
