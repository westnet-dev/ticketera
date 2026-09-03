<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketImage;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $title = '';

    public string $description = '';

    public $priority;

    public $urgency;

    public $impact;

    public array $imagesToRemove = [];

    public array $newImages = [];

    public function mount(): void
    {
        $this->title = $this->ticket->title;
        $this->description = $this->ticket->description;
        $this->priority = $this->ticket->priority;
        $this->urgency = $this->ticket->urgency;
        $this->impact = $this->ticket->impact;
    }

    public function save(): void
    {
        Gate::authorize('reviseTriage', $this->ticket);

        $validated = $this->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10',
            'priority' => 'required|integer|min:1|max:10',
            'urgency' => 'required|integer|min:1|max:10',
            'impact' => 'required|integer|min:1|max:10',
            'newImages.*' => 'image|max:2048',
        ]);

        $remainingCount = $this->ticket->images()->whereNotIn('id', $this->imagesToRemove)->count();
        $resultingCount = $remainingCount + count($this->newImages);

        if ($resultingCount < 1 || $resultingCount > 5) {
            $this->addError('newImages', __('El ticket debe tener entre 1 y 5 imágenes en total.'));

            return;
        }

        $this->ticket->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'priority' => $validated['priority'],
            'urgency' => $validated['urgency'],
            'impact' => $validated['impact'],
            'triage_status' => TriageStatus::Pending,
        ]);

        TicketImage::query()
            ->whereIn('id', $this->imagesToRemove)
            ->where('ticket_id', $this->ticket->id)
            ->get()
            ->each(fn (TicketImage $image) => $image->deleteWithFile());

        foreach ($this->newImages as $image) {
            TicketImage::create([
                'ticket_id' => $this->ticket->id,
                'image_path' => $image->store('tickets', 'public'),
            ]);
        }

        $this->reset(['imagesToRemove', 'newImages']);
    }
};
?>

<div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
    @if ($ticket->isTriageRejected())
        <flux:heading size="sm">{{ __('Tu ticket fue rechazado') }}</flux:heading>
        <flux:subheading>{{ __('Revisá el motivo en el chat, corregí lo que haga falta y reenvialo a triage.') }}</flux:subheading>

        <form wire:submit="save" class="mt-4 flex flex-col gap-5">
            <flux:field>
                <flux:label>{{ __('Título') }}</flux:label>
                <flux:input wire:model="title" />
                <flux:error name="title" />
            </flux:field>

            <flux:textarea wire:model="description" :label="__('Descripción')" />

            <flux:input wire:model="priority" type="number" min="1" max="10" label="{{ __('Prioridad (1-10)') }}" />

            <flux:input wire:model="urgency" type="number" min="1" max="10" label="{{ __('Urgencia (1-10)') }}" />

            <flux:input wire:model="impact" type="number" min="1" max="10" label="{{ __('Impacto (1-10)') }}" />

            @if ($ticket->images->isNotEmpty())
                <flux:field>
                    <flux:label>{{ __('Imágenes actuales') }}</flux:label>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($ticket->images as $image)
                            <label class="flex flex-col items-center gap-1 text-xs">
                                <img
                                    src="{{ $image->getImageUrlAttribute() }}"
                                    alt="{{ $ticket->title }}"
                                    class="h-20 w-20 rounded-lg object-cover"
                                />
                                <span class="flex items-center gap-1">
                                    <input type="checkbox" wire:model="imagesToRemove" value="{{ $image->id }}" />
                                    {{ __('Eliminar') }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Agregar imágenes') }}</flux:label>
                <input
                    type="file"
                    wire:model="newImages"
                    multiple
                    class="block w-full rounded-lg border border-zinc-200 text-sm text-zinc-600 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600"
                >
                <flux:error name="newImages" />
                <flux:error name="newImages.*" />
            </flux:field>

            <flux:button type="submit" variant="primary">{{ __('Reenviar a triage') }}</flux:button>
        </form>
    @else
        <flux:text>{{ __('Ya reenviaste este ticket a triage. Un admin lo va a revisar de nuevo.') }}</flux:text>
    @endif
</div>
