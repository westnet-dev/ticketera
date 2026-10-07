<x-layouts::app :title="$ticket->title">
    <div class="flex h-full flex-col gap-3 lg:grid lg:grid-cols-5">
        <div class="contents lg:col-span-4 lg:flex lg:h-full lg:w-full lg:flex-1 lg:flex-col lg:gap-4 lg:rounded-xl">
            <div class="order-1 flex min-w-0 flex-col gap-4">
                <x-page-header :title="$ticket->title">
                    <x-slot:description>
                        <span class="inline-flex flex-wrap items-center gap-2">
                            <span class="text-xs text-neutral-400">#TK-{{ $ticket->id }}</span>
                            <flux:badge size="sm" :color="$ticket->statusColor()">{{ $ticket->statusLabel() }}</flux:badge>
                            <x-tickets.priority-indicator :priority="$ticket->priority" :ticket="$ticket" />
                            @unless ($ticket->isTriageApproved())
                                <flux:badge size="sm" :color="$ticket->triageStatusColor()">{{ $ticket->triageStatusLabel() }}</flux:badge>
                            @endunless
                        </span>
                    </x-slot:description>

                    <x-slot:actions>
                        @can('edit', $ticket)
                            <livewire:tickets.edit-ticket :ticket="$ticket" />
                        @endcan

                        <flux:button
                            href="{{ route('ticket.index') }}"
                            wire:navigate
                            variant="outline"
                            size="sm"
                            icon="arrow-left"
                        >
                            {{ __("Volver a mis tickets") }}
                        </flux:button>
                    </x-slot:actions>
                </x-page-header>

                @can('validateResolution', $ticket)
                    <flux:callout icon="check-badge" variant="warning">
                        <flux:callout.heading>{{ __('Este ticket está esperando tu validación') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('El equipo marcó el ticket como resuelto. Confirmá si el pedido quedó resuelto y calificá la solución, o indicá qué quedó pendiente.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endcan

                <x-panel :heading="__('Descripción')">
                    <div class="flex flex-col gap-4 p-4">
                        <x-tickets.rich-text :html="$ticket->description" class="w-full text-neutral-700 dark:text-neutral-300" />

                        @if ($ticket->images->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($ticket->images as $image)
                                    <flux:modal.trigger name="ticket-image-{{ $image->id }}">
                                        <button type="button" class="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-700">
                                            <img
                                                src="{{ $image->getImageUrlAttribute() }}"
                                                alt="{{ $ticket->title }}"
                                                class="h-20 w-20 object-cover"
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
                    </div>
                </x-panel>

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
        <div class="contents lg:col-span-1 lg:flex lg:flex-col lg:gap-4">
            <x-panel :heading="__('Propiedades')" class="order-2">
                <dl class="flex flex-col gap-3 p-4 text-sm">
                    <div>
                        <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Cliente') }}</dt>
                        <dd class="mt-1"><x-user-cell :user="$ticket->user" /></dd>
                    </div>
                    @if ($ticket->wasCreatedOnBehalf())
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Creado por :author en nombre de :owner', [
                                'author' => $ticket->createdBy?->name ?? __('un usuario eliminado'),
                                'owner' => $ticket->user->name,
                            ]) }}
                        </p>
                    @endif
                    <div>
                        <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Estado') }}</dt>
                        <dd class="mt-1 flex flex-wrap items-center gap-2">
                            @can('changeStatus', $ticket)
                                <livewire:tickets.ticket-status-selector :ticket="$ticket" />
                            @else
                                <flux:badge size="sm" :color="$ticket->statusColor()">
                                    {{ $ticket->statusLabel() }}
                                </flux:badge>
                            @endcan
                            @unless ($ticket->isTriageApproved())
                                <flux:badge size="sm" :color="$ticket->triageStatusColor()">
                                    {{ $ticket->triageStatusLabel() }}
                                </flux:badge>
                            @endunless
                        </dd>
                    </div>

                    @if (auth()->user()->isAdmin())
                        <div>
                            <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Asignado a') }}</dt>
                            <dd class="mt-1">
                                @can('assign', $ticket)
                                    <livewire:tickets.ticket-assignee-selector :ticket="$ticket" />
                                @else
                                    <span class="text-neutral-900 dark:text-white">
                                        {{ $ticket->assigned_to === null ? __('Sin asignar') : ($ticket->assignedTo?->name ?? __('un usuario eliminado')) }}
                                    </span>
                                @endcan
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Colaboradores') }}</dt>
                            <dd class="mt-1">
                                <livewire:tickets.ticket-collaborators :ticket="$ticket" />
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Prioridad') }}</dt>
                        <dd class="mt-1"><x-tickets.priority-indicator :priority="$ticket->priority" /></dd>
                    </div>

                    @can('estimate', $ticket)
                        <div>
                            <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Dificultad') }}</dt>
                            <dd class="mt-1 flex flex-wrap items-center gap-2">
                                <livewire:tickets.ticket-difficulty-selector :ticket="$ticket" />
                            </dd>
                        </div>
                    @endcan

                    <div>
                        <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Categoría') }}</dt>
                        <dd class="mt-1"><x-tickets.category-badge :category="$ticket->category" /></dd>
                    </div>

                    <div>
                        <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Área') }}</dt>
                        <dd class="mt-1 text-neutral-900 dark:text-white">{{ $ticket->area?->title ?? __('Sin área') }}</dd>
                    </div>

                    @if ($ticket->validationWasRequested())
                        <div>
                            <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Validación') }}</dt>
                            <dd class="mt-1">
                                <flux:badge size="sm" :color="$ticket->validationStatusColor()">
                                    {{ $ticket->validationStatusLabel() }}
                                </flux:badge>
                            </dd>
                        </div>
                    @endif

                    @if ($ticket->resolution_rating !== null)
                        <div>
                            <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Calificación') }}</dt>
                            <dd class="mt-1 flex items-center gap-0.5 text-yellow-500">
                                @for ($star = 1; $star <= 5; $star++)
                                    <flux:icon.star
                                        variant="{{ $star <= $ticket->resolution_rating ? 'solid' : 'outline' }}"
                                        class="size-4"
                                    />
                                @endfor
                            </dd>
                        </div>
                    @endif

                    <div class="grid grid-cols-3 gap-2 border-t border-neutral-200 pt-3 dark:border-neutral-700">
                        @foreach ([__('Importancia') => $ticket->importance, __('Urgencia') => $ticket->urgency, __('Impacto') => $ticket->impact] as $label => $level)
                            <div>
                                <dt class="text-xs text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                                <dd class="mt-0.5 font-semibold">{{ $level->label() }}</dd>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-col gap-1 border-t border-neutral-200 pt-3 text-xs text-neutral-500 dark:border-neutral-700 dark:text-neutral-400">
                        <p>{{ __('Creación') }}: {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
                        <p>{{ __('Actualización') }}: {{ $ticket->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                </dl>

                @can('approve', $ticket)
                    <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                        <p class="mb-3 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Acciones') }}</p>
                        <livewire:tickets.ticket-triage-actions :ticket="$ticket" />
                    </div>
                @endcan

                @can('validateResolution', $ticket)
                    <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                        <p class="mb-3 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Acciones') }}</p>
                        <livewire:tickets.ticket-validation-actions :ticket="$ticket" />
                    </div>
                @endcan
            </x-panel>

            <x-panel class="order-4 p-4">
                <livewire:tickets.ticket-history-timeline :ticket="$ticket" />
            </x-panel>
        </div>
    </div>
</x-layouts::app>
