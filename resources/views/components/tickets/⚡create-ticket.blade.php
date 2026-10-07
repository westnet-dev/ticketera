<?php

use App\Enums\Level;
use App\Enums\Role;
use App\Enums\TriageStatus;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketImage;
use App\Models\TicketSetting;
use App\Models\User;
use App\Rules\RichTextLength;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
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
    public $importance = Level::Medium->value;
    public $urgency = Level::Medium->value;
    public $impact = Level::Medium->value;
    public $category_id = '';
    public $area_id = '';
    public $images = [];
    public $author_id = '';

    public function mount(?Ticket $draft = null): void
    {
        $this->draft = $draft;

        if ($draft) {
            $this->title = $draft->title;
            $this->description = $draft->description;
            $this->importance = $draft->importance->value;
            $this->urgency = $draft->urgency->value;
            $this->impact = $draft->impact->value;
            $this->category_id = $draft->category_id ?? '';
            $this->area_id = $draft->area_id ?? '';
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
     * A different author can bring different areas, so the one picked for the previous author no longer applies.
     */
    public function updatedAuthorId(): void
    {
        $this->reset('area_id');
    }

    /**
     * The user the ticket is filed for: the client an admin picked, or whoever is filing it.
     */
    private function effectiveAuthorId(): int
    {
        return $this->resolvedAuthorId() ?? auth()->id();
    }

    /**
     * The areas the ticket can be filed for, which are always its author's.
     *
     * @return Collection<int, Area>
     */
    private function authorAreas(): Collection
    {
        return Area::query()
            ->whereRelation('users', 'users.id', $this->effectiveAuthorId())
            ->orderBy('title')
            ->get();
    }

    /**
     * The area the ticket goes to.
     *
     * An author with a single area never sees the picker, so that area is used
     * as is; one with several areas must have picked one, already validated.
     */
    private function resolvedArea(): ?Area
    {
        $areas = $this->authorAreas();

        if ($areas->count() <= 1) {
            return $areas->first();
        }

        return $areas->firstWhere('id', (int) $this->area_id);
    }

    /**
     * Picking an area is only mandatory when the author has more than one, and
     * the pick is checked against the author's areas since the client can send any value.
     *
     * @return array{area_id: array<int, mixed>}
     */
    private function areaRules(bool $required): array
    {
        $authorId = $this->effectiveAuthorId();

        return [
            'area_id' => [
                $required && $this->authorAreas()->count() > 1 ? 'required' : 'nullable',
                'integer',
                Rule::exists('area_user', 'area_id')->where('user_id', $authorId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function areaMessages(): array
    {
        return [
            'area_id.required' => __('Elegí para qué área es el ticket.'),
            'area_id.exists' => __('El área elegida no corresponde al autor del ticket.'),
        ];
    }

    /**
     * The picked category, with the select's empty "Sin categoría" option mapped to null.
     */
    private function resolvedCategoryId(): ?int
    {
        return $this->category_id === null || $this->category_id === '' ? null : (int) $this->category_id;
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
     * Names whose cap filled up, because a user can be blocked by an area
     * without holding a single ticket of their own, and one with several
     * areas needs to know which one is full to pick another.
     */
    private function limitReachedMessage(string $action, ?Area $area): string
    {
        $max = TicketSetting::current()->max_open_tickets_per_area;

        return $area !== null
            ? __('El área :area alcanzó el máximo de :max tickets sin cerrar permitidos. :action', ['area' => $area->title, 'max' => $max, 'action' => $action])
            : __('Alcanzaste el máximo de :max tickets sin cerrar permitidos. :action', ['max' => $max, 'action' => $action]);
    }

    public function validateInput()
    {
        $this->validate([
            'author_id' => ['nullable', Rule::exists('users', 'id')->where('role', Role::Client->value)->whereNull('deleted_at')],
            'title' => 'required|string|min:5|max:255',
            'description' => ['required', 'string', new RichTextLength(min: 10)],
            'importance' => ['required', Rule::enum(Level::class)],
            'urgency' => ['required', Rule::enum(Level::class)],
            'impact' => ['required', Rule::enum(Level::class)],
            'category_id' => 'nullable|integer|exists:ticket_categories,id',
            ...$this->areaRules(required: true),
            ...$this->imageRules(),
        ], [...$this->areaMessages(), ...$this->imageMessages()]);
    }

    public function save()
    {
        $authorId = $this->resolvedAuthorId();

        if ($authorId !== null) {
            Gate::authorize('createForOthers', Ticket::class);
        }

        $this->validateInput();

        $area = $this->resolvedArea();

        try {
            Gate::authorize('create', [Ticket::class, $area]);
        } catch (AuthorizationException $e) {
            $this->addError('title', $this->limitReachedMessage(__('Cerrá alguno para poder crear uno nuevo.'), $area));

            return;
        }

        $ticket = Ticket::create([
            'user_id' => $authorId ?? auth()->id(),
            'area_id' => $area?->id,
            'created_by' => auth()->id(),
            'title' => $this->title,
            'description' => $this->description,
            'importance' => (int) $this->importance,
            'urgency' => (int) $this->urgency,
            'impact' => (int) $this->impact,
            'category_id' => $this->resolvedCategoryId(),
            'triage_status' => auth()->user()->isAdmin() ? TriageStatus::Approved : TriageStatus::Pending,
        ]);

        $this->storeUploadedImages($ticket);

        $this->reset(['title', 'description', 'importance', 'urgency', 'impact', 'category_id', 'area_id', 'images', 'author_id']);

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
            'importance' => ['nullable', Rule::enum(Level::class)],
            'urgency' => ['nullable', Rule::enum(Level::class)],
            'impact' => ['nullable', Rule::enum(Level::class)],
            'category_id' => 'nullable|integer|exists:ticket_categories,id',
            ...$this->areaRules(required: false),
            ...$this->imageRules(),
        ], [...$this->areaMessages(), ...$this->imageMessages()]);

        $attributes = [
            'title' => $this->title,
            'description' => $this->description ?: null,
            'importance' => filled($this->importance) ? (int) $this->importance : Level::Medium,
            'urgency' => filled($this->urgency) ? (int) $this->urgency : Level::Medium,
            'impact' => filled($this->impact) ? (int) $this->impact : Level::Medium,
            'category_id' => $this->resolvedCategoryId(),
            'area_id' => $this->resolvedArea()?->id,
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

        $this->validateInput();

        $area = $this->resolvedArea();

        try {
            Gate::authorize('create', [Ticket::class, $area]);
        } catch (AuthorizationException $e) {
            $this->addError('title', $this->limitReachedMessage(__('Cerrá alguno para poder enviar este borrador.'), $area));

            return;
        }

        DB::transaction(fn () => $this->draft->update([
            'title' => $this->title,
            'description' => $this->description,
            'importance' => (int) $this->importance,
            'urgency' => (int) $this->urgency,
            'impact' => (int) $this->impact,
            'category_id' => $this->resolvedCategoryId(),
            'area_id' => $area?->id,
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

        return redirect()->route('ticket.index', ['statuses' => ['draft']]);
    }

    /**
     * @return array{clients: \Illuminate\Support\Collection<int, User>, categories: \Illuminate\Support\Collection<int, TicketCategory>, authorAreas: Collection<int, Area>}
     */
    public function with(): array
    {
        return [
            'authorAreas' => $this->authorAreas(),
            'categories' => TicketCategory::orderBy('name')->get(),
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

<div class="w-full rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-900">
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

            @if ($authorAreas->count() > 1)
                <flux:field>
                    <flux:label>{{ __('Área') }}</flux:label>
                    <flux:description>{{ __('Elegí para qué área es este ticket.') }}</flux:description>
                    <flux:select wire:model="area_id">
                        <flux:select.option value="">{{ __('Elegir un área') }}</flux:select.option>
                        @foreach ($authorAreas as $areaOption)
                            <flux:select.option :key="$areaOption->id" value="{{ $areaOption->id }}">
                                {{ $areaOption->title }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="area_id" />
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
                <x-tickets.rich-editor wire:model="description" />
                <flux:error name="description" />
            </flux:field>

            @if ($categories->isNotEmpty())
                <flux:field>
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Categoría') }}</flux:label>
                    <flux:description>{{ __('Elegí el tipo de pedido que mejor describe tu ticket.') }}</flux:description>
                    <flux:select wire:model="category_id">
                        <flux:select.option value="">{{ __('Sin categoría') }}</flux:select.option>
                        @foreach ($categories as $category)
                            <flux:select.option :key="$category->id" value="{{ $category->id }}">
                                {{ $category->name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="category_id" />
                </flux:field>
            @endif

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

        <x-tickets.level-select
            wire:model.live="importance"
            :label="__('Importancia')"
            :description="__('Indicá qué tan importante es este pedido respecto de tus otros pedidos.')"
        />

        <x-tickets.level-select
            wire:model.live="urgency"
            :label="__('Urgencia')"
            :description="__('Indicá qué tan rápido necesitás el caso resuelto en relación a tu trabajo diario.')"
        />

        <x-tickets.level-select
            wire:model="impact"
            :label="__('Impacto')"
            :description="__('Indicá cuánto afecta a los usuarios, donde baja es a pocos usuarios y alta es a muchos usuarios.')"
        />

        <x-tickets.priority-preview :importance="$importance" :urgency="$urgency" class="md:col-span-3" />

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
