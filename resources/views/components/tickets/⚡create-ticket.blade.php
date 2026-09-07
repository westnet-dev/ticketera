<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\TicketSetting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public ?Ticket $draft = null;

    public $title;
    public $description;
    public $priority;
    public $urgency;
    public $impact;
    public $images = [];

    public function mount(?Ticket $draft = null): void
    {
        $this->draft = $draft;

        if ($draft) {
            $this->title = $draft->title;
            $this->description = $draft->description;
            $this->priority = $draft->priority;
            $this->urgency = $draft->urgency;
            $this->impact = $draft->impact;
        }
    }

    public function validateInput()
    {
        $hasExistingImages = $this->draft && $this->draft->images()->exists();

        $this->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10',
            'priority' => 'required|integer|min:1|max:10',
            'urgency' => 'required|integer|min:1|max:10',
            'impact' => 'required|integer|min:1|max:10',
            'images' => [$hasExistingImages ? 'nullable' : 'required', 'array', 'max:5'],
            'images.*' => 'image|max:2048', // Each image must be an image file and not exceed 2MB
        ]);
    }

    public function save()
    {
        try {
            Gate::authorize('create', Ticket::class);
        } catch (AuthorizationException $e) {
            $this->addError('title', __('Alcanzaste el máximo de :max tickets sin cerrar permitidos. Cerrá alguno para poder crear uno nuevo.', ['max' => TicketSetting::current()->max_open_tickets_per_user]));

            return;
        }

        $this->validateInput();

        $ticket = auth()->user()->tickets()->create([
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'urgency' => $this->urgency,
            'impact' => $this->impact,
            'triage_status' => auth()->user()->isAdmin() ? TriageStatus::Approved : TriageStatus::Pending,
        ]);

        $this->storeUploadedImages($ticket);

        $this->reset(['title', 'description', 'priority', 'urgency', 'impact', 'images']);

        session()->flash('message', 'Ticket created successfully!');
    }

    public function saveDraft()
    {
        $this->validate([
            'title' => 'required|string|min:5|max:255',
        ]);

        $attributes = [
            'title' => $this->title,
            'description' => $this->description ?: null,
            'priority' => $this->priority !== null && $this->priority !== '' ? (int) $this->priority : 5,
            'urgency' => $this->urgency !== null && $this->urgency !== '' ? (int) $this->urgency : 5,
            'impact' => $this->impact !== null && $this->impact !== '' ? (int) $this->impact : 5,
        ];

        if ($this->draft) {
            Gate::authorize('update', $this->draft);

            $this->draft->update($attributes);
            $this->storeUploadedImages($this->draft);
            $this->reset(['images']);

            session()->flash('message', __('Borrador guardado.'));

            return;
        }

        $draft = auth()->user()->tickets()->create([...$attributes, 'status' => 'draft']);

        $this->storeUploadedImages($draft);

        return redirect()->route('ticket.show', $draft);
    }

    public function submit()
    {
        if (! $this->draft) {
            return;
        }

        Gate::authorize('update', $this->draft);

        try {
            Gate::authorize('create', Ticket::class);
        } catch (AuthorizationException $e) {
            $this->addError('title', __('Alcanzaste el máximo de :max tickets sin cerrar permitidos. Cerrá alguno para poder enviar este borrador.', ['max' => TicketSetting::current()->max_open_tickets_per_user]));

            return;
        }

        $this->validateInput();

        $this->draft->update([
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'urgency' => $this->urgency,
            'impact' => $this->impact,
            'status' => 'open',
            'triage_status' => auth()->user()->isAdmin() ? TriageStatus::Approved : TriageStatus::Pending,
        ]);

        $this->storeUploadedImages($this->draft);

        return redirect()->route('ticket.show', $this->draft);
    }

    public function deleteDraft()
    {
        Gate::authorize('delete', $this->draft);

        $this->draft->delete();

        return redirect()->route('ticket.drafts');
    }

    private function storeUploadedImages(Ticket $ticket): void
    {
        foreach ($this->images as $image) {
            TicketImage::create([
                'ticket_id' => $ticket->id,
                'image_path' => $image->store('tickets', 'public'),
            ]);
        }
    }
};
?>

<div class="max-w-6xl mx-auto rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
    @if (session('message'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="{{ $draft ? 'submit' : 'save' }}" class="gap-5 grid md:grid-cols-3">
        <div class="flex flex-col gap-5 col-span-3">
            <flux:field>
                <flux:label>Título</flux:label>
                <flux:description class="">Indica un título que resuma tu solicitud o problema.</flux:description>
                <flux:input wire:model="title" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>Descripción</flux:label>
                <flux:description class="">Describe tu solicitud o problema con la mayor cantidad de detalles posibles.</flux:description>
                <flux:textarea wire:model="description" />
                <flux:error name="description" />
            </flux:field>

            <flux:field>
                <flux:label>Imágenes</flux:label>
                <input
                    type="file"
                    wire:model="images"
                    multiple
                    class="block w-full rounded-lg border border-zinc-200 text-sm text-zinc-600 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600"
                >
                <flux:error name="images" />
                <flux:error name="images.*" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Prioridad (1-10)</flux:label>
            <flux:description>Indica respecto a tus pedudos que importancia tienen.</flux:description>
            <flux:input wire:model="priority" type="number" min="1" max="10" />
            <flux:error name="priority" />
        </flux:field>

        <flux:field>
            <flux:label>Urgencia (1-10)</flux:label>
            <flux:description>Indica qué tan rápido necesitas el caso resuelto</flux:description>
            <flux:input wire:model="urgency" type="number" min="1" max="10" />
            <flux:error name="urgency" />
        </flux:field>

            <flux:field>
                <flux:label>Impacto (1-10)</flux:label>
                <flux:description>Indica que tanto afecta el caso a los clientes o negocio.</flux:description>
                <flux:input wire:model="impact" type="number" min="1" max="10" />
                <flux:error name="impact" />
            </flux:field>

        <div class="col-start-2 flex items-center gap-2">
            @if ($draft)
                <flux:button type="button" variant="ghost" wire:click="deleteDraft" wire:confirm="{{ __('¿Eliminar este borrador?') }}">
                    {{ __('Eliminar borrador') }}
                </flux:button>
                <flux:button type="button" variant="filled" wire:click="saveDraft">
                    {{ __('Guardar como borrador') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Enviar') }}
                </flux:button>
            @else
                <flux:button type="button" variant="filled" wire:click="saveDraft">
                    {{ __('Guardar como borrador') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Crear Ticket') }}
                </flux:button>
            @endif
        </div>
    </form>
</div>
