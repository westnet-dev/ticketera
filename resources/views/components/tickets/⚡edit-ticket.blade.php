<?php

use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    /**
     * Total images a ticket may hold, the same cap that applies when creating it.
     */
    private const MAX_IMAGES = 5;

    /**
     * Fields whose edits are summarized in the ticket history.
     *
     * @var array<int, string>
     */
    private const CONTENT_FIELDS = ['title', 'description', 'priority', 'urgency', 'impact'];

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
        $this->description = (string) $this->ticket->description;
        $this->priority = $this->ticket->priority;
        $this->urgency = $this->ticket->urgency;
        $this->impact = $this->ticket->impact;
    }

    public function save(): void
    {
        Gate::authorize('edit', $this->ticket);

        $validated = $this->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10',
            'priority' => 'required|integer|min:1|max:10',
            'urgency' => 'required|integer|min:1|max:10',
            'impact' => 'required|integer|min:1|max:10',
            'imagesToRemove.*' => 'integer',
            'newImages.*' => 'image|max:2048',
        ]);

        $remainingCount = $this->ticket->images()->whereNotIn('id', $this->imagesToRemove)->count();

        if ($remainingCount + count($this->newImages) > self::MAX_IMAGES) {
            $this->addError('newImages', __('El ticket puede tener hasta :total imágenes en total.', ['total' => self::MAX_IMAGES]));

            return;
        }

        $storedPaths = collect($this->newImages)
            ->map(fn ($image) => $image->store('tickets', 'public'))
            ->all();

        try {
            $removedPaths = DB::transaction(function () use ($validated, $storedPaths) {
                $ticket = Ticket::query()->lockForUpdate()->findOrFail($this->ticket->id);

                Gate::authorize('edit', $ticket);

                $ticket->fill(collect($validated)->only(self::CONTENT_FIELDS)->all());

                $changedFields = array_values(array_intersect(self::CONTENT_FIELDS, array_keys($ticket->getDirty())));

                $ticket->save();

                foreach ($storedPaths as $path) {
                    $ticket->images()->create(['image_path' => $path]);
                }

                $removedImages = $ticket->images()->whereIn('id', $this->imagesToRemove)->get();
                $ticket->images()->whereKey($removedImages->modelKeys())->delete();

                if ($storedPaths !== [] || $removedImages->isNotEmpty()) {
                    $changedFields[] = 'images';
                }

                if ($changedFields !== []) {
                    TicketHistory::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => auth()->id(),
                        'field' => 'details',
                        'from_value' => null,
                        'to_value' => implode(',', $changedFields),
                    ]);
                }

                return $removedImages->pluck('image_path')->all();
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($storedPaths);

            throw $e;
        }

        Storage::disk('public')->delete($removedPaths);

        $this->reset(['imagesToRemove', 'newImages']);

        $this->redirect(route('ticket.show', $this->ticket), navigate: true);
    }
};
?>

<div>
    <flux:modal.trigger name="edit-ticket">
        <flux:button variant="outline" size="sm" icon="pencil-square">
            {{ __('Editar') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="edit-ticket" class="w-full max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-5">
            <div>
                <flux:heading size="lg">{{ __('Editar ticket') }}</flux:heading>
                <flux:subheading>{{ __('Los cambios quedan registrados en el historial del ticket.') }}</flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Título') }}</flux:label>
                <flux:description>{{ __('Indica un título que resuma tu solicitud o problema.') }}</flux:description>
                <flux:input wire:model="title" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Descripción') }}</flux:label>
                <flux:description>{{ __('Describe tu solicitud o problema con la mayor cantidad de detalles posibles.') }}</flux:description>
                <flux:textarea wire:model="description" />
                <flux:error name="description" />
            </flux:field>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Prioridad (1-10)') }}</flux:label>
                    <flux:input wire:model="priority" type="number" min="1" max="10" />
                    <flux:error name="priority" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Urgencia (1-10)') }}</flux:label>
                    <flux:input wire:model="urgency" type="number" min="1" max="10" />
                    <flux:error name="urgency" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Impacto (1-10)') }}</flux:label>
                    <flux:input wire:model="impact" type="number" min="1" max="10" />
                    <flux:error name="impact" />
                </flux:field>
            </div>

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
                <flux:label badge="{{ __('Opcional') }}">{{ __('Agregar imágenes') }}</flux:label>
                <flux:description>{{ __('El ticket puede tener hasta 5 imágenes de hasta 2 MB cada una.') }}</flux:description>
                <input
                    type="file"
                    wire:model="newImages"
                    accept="image/*"
                    multiple
                    class="block w-full rounded-lg border border-zinc-200 text-sm text-zinc-600 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:border-zinc-700 dark:text-zinc-300 dark:file:bg-zinc-700 dark:file:text-zinc-200 dark:hover:file:bg-zinc-600"
                >
                <flux:error name="newImages" />
                <flux:error name="newImages.*" />
            </flux:field>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancelar') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">
                    {{ __('Guardar cambios') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
