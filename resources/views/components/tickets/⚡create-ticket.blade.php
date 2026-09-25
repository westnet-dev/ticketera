<?php

use App\Enums\Role;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketImage;
use App\Models\TicketSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    /**
     * Total images a ticket may hold, counting the ones already attached to a draft.
     */
    private const MAX_IMAGES = 5;

    public ?Ticket $draft = null;

    public $title;
    public $description;
    public $priority;
    public $urgency;
    public $impact;
    public $images = [];
    public $author_id = '';

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

    /**
     * The author an admin picked to file this ticket on behalf of, if any.
     */
    private function resolvedAuthorId(): ?int
    {
        if ($this->author_id === null || $this->author_id === '') {
            return null;
        }

        $authorId = (int) $this->author_id;

        return $authorId === auth()->id() ? null : $authorId;
    }

    /**
     * How many more images can still be attached before hitting the per-ticket cap.
     */
    private function availableImageSlots(): int
    {
        $existing = $this->draft ? $this->draft->images()->count() : 0;

        return max(0, self::MAX_IMAGES - $existing);
    }

    /**
     * @return array{images: array<int, string>, 'images.*': string}
     */
    private function imageRules(): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.$this->availableImageSlots()],
            'images.*' => 'image|max:2048', // Each image must be an image file and not exceed 2MB
        ];
    }

    /**
     * @return array<string, string>
     */
    private function imageMessages(): array
    {
        return [
            'images.max' => __('Un ticket puede tener hasta :total imágenes en total.', ['total' => self::MAX_IMAGES]),
        ];
    }

    /**
     * The blocking message shown when the ticket cap is reached.
     *
     * Names whose cap filled up, because a user with an area can be blocked
     * without holding a single ticket of their own.
     */
    private function limitReachedMessage(string $action): string
    {
        $max = TicketSetting::current()->max_open_tickets_per_area;

        return auth()->user()->ticketLimitIsPerArea()
            ? __('Tu área alcanzó el máximo de :max tickets sin cerrar permitidos. :action', ['max' => $max, 'action' => $action])
            : __('Alcanzaste el máximo de :max tickets sin cerrar permitidos. :action', ['max' => $max, 'action' => $action]);
    }

    public function validateInput()
    {
        $this->validate([
            'author_id' => ['nullable', Rule::exists('users', 'id')->where('role', Role::Client->value)->whereNull('deleted_at')],
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:10',
            'priority' => 'required|integer|min:1|max:10',
            'urgency' => 'required|integer|min:1|max:10',
            'impact' => 'required|integer|min:1|max:10',
            ...$this->imageRules(),
        ], $this->imageMessages());
    }

    public function save()
    {
        $authorId = $this->resolvedAuthorId();

        if ($authorId !== null) {
            Gate::authorize('createForOthers', Ticket::class);
        }

        try {
            Gate::authorize('create', Ticket::class);
        } catch (AuthorizationException $e) {
            $this->addError('title', $this->limitReachedMessage(__('Cerrá alguno para poder crear uno nuevo.')));

            return;
        }

        $this->validateInput();

        $ticket = Ticket::create([
            'user_id' => $authorId ?? auth()->id(),
            'created_by' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'urgency' => $this->urgency,
            'impact' => $this->impact,
            'triage_status' => auth()->user()->isAdmin() ? TriageStatus::Approved : TriageStatus::Pending,
        ]);

        $this->storeUploadedImages($ticket);

        $this->reset(['title', 'description', 'priority', 'urgency', 'impact', 'images', 'author_id']);

        session()->flash('message', $authorId !== null
            ? __('Ticket creado a nombre de :name.', ['name' => $ticket->user->name])
            : 'Ticket created successfully!');
    }

    public function saveDraft()
    {
        if ($this->resolvedAuthorId() !== null) {
            $this->addError('author_id', __('No se puede guardar como borrador un ticket a nombre de otro usuario.'));

            return;
        }

        $this->validate([
            'title' => 'required|string|min:5|max:255',
            ...$this->imageRules(),
        ], $this->imageMessages());

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

        $draft = auth()->user()->tickets()->create([...$attributes, 'created_by' => auth()->id(), 'status' => 'draft']);

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
            $this->addError('title', $this->limitReachedMessage(__('Cerrá alguno para poder enviar este borrador.')));

            return;
        }

        $this->validateInput();

        DB::transaction(fn () => $this->draft->update([
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'urgency' => $this->urgency,
            'impact' => $this->impact,
            'status' => 'open',
            'triage_status' => auth()->user()->isAdmin() ? TriageStatus::Approved : TriageStatus::Pending,
        ]));

        $this->storeUploadedImages($this->draft);

        return redirect()->route('ticket.show', $this->draft);
    }

    public function deleteDraft()
    {
        Gate::authorize('delete', $this->draft);

        $this->draft->delete();

        return redirect()->route('ticket.drafts');
    }

    /**
     * @return array{clients: \Illuminate\Support\Collection<int, User>}
     */
    public function with(): array
    {
        return [
            'clients' => auth()->user()->isAdmin()
                ? User::query()->role(Role::Client->value)->orderBy('name')->get()
                : collect(),
        ];
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

<div class="max-w-6xl mx-auto w-full rounded-xl border border-neutral-200 p-4 sm:p-6 dark:border-neutral-700">
    @if (session('message'))
        <div class="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-400">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit.prevent="{{ $draft ? 'submit' : 'save' }}" class="grid grid-cols-1 gap-5 md:grid-cols-3">
        <div class="flex flex-col gap-5 md:col-span-3">
            @if (auth()->user()->isAdmin() && ! $draft)
                <flux:field>
                    <flux:label>{{ __('Autor') }}</flux:label>
                    <flux:description>
                        {{ __('Elegí un cliente para cargar el ticket a su nombre. Dejalo en vos para que el ticket sea tuyo.') }}
                    </flux:description>
                    <flux:select wire:model.live="author_id">
                        <flux:select.option value="">
                            {{ __('Yo (:name)', ['name' => auth()->user()->name]) }}
                        </flux:select.option>
                        @foreach ($clients as $client)
                            <flux:select.option :key="$client->id" value="{{ $client->id }}">
                                {{ $client->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="author_id" />
                </flux:field>
            @endif

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
                <flux:label badge="{{ __('Opcional') }}">Imágenes</flux:label>
                <flux:description>{{ __('Podés adjuntar hasta 5 imágenes de hasta 2 MB cada una.') }}</flux:description>
                <input
                    type="file"
                    wire:model="images"
                    accept="image/*"
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

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end md:col-span-3">
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
                @unless ($author_id)
                    <flux:button type="button" variant="filled" wire:click="saveDraft">
                        {{ __('Guardar como borrador') }}
                    </flux:button>
                @endunless
                <flux:button type="submit" variant="primary">
                    {{ __('Crear Ticket') }}
                </flux:button>
            @endif
        </div>
    </form>
</div>
