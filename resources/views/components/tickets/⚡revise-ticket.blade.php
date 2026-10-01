<?php

use App\Enums\Level;
use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketImage;
use App\Rules\RichTextLength;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $title = '';

    public string $description = '';

    public $importance;

    public $urgency;

    public $impact;

    public $category_id = '';

    public array $imagesToRemove = [];

    public array $newImages = [];

    public function mount(): void
    {
        $this->title = $this->ticket->title;
        $this->description = (string) $this->ticket->description;
        $this->importance = $this->ticket->importance->value;
        $this->urgency = $this->ticket->urgency->value;
        $this->impact = $this->ticket->impact->value;
        $this->category_id = $this->ticket->category_id ?? '';
    }

    public function save(): void
    {
        Gate::authorize('reviseTriage', $this->ticket);

        $validated = $this->validate([
            'title' => 'required|string|min:5|max:255',
            'description' => ['required', 'string', new RichTextLength(min: 10)],
            'importance' => ['required', Rule::enum(Level::class)],
            'urgency' => ['required', Rule::enum(Level::class)],
            'impact' => ['required', Rule::enum(Level::class)],
            'category_id' => 'nullable|integer|exists:ticket_categories,id',
            'newImages.*' => 'image|max:2048',
        ]);

        $remainingCount = $this->ticket->images()->whereNotIn('id', $this->imagesToRemove)->count();
        $resultingCount = $remainingCount + count($this->newImages);

        if ($resultingCount > 5) {
            $this->addError('newImages', __('El ticket puede tener hasta 5 imágenes en total.'));

            return;
        }

        DB::transaction(fn () => $this->ticket->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'importance' => $validated['importance'],
            'urgency' => $validated['urgency'],
            'impact' => $validated['impact'],
            'category_id' => filled($validated['category_id']) ? (int) $validated['category_id'] : null,
            'triage_status' => TriageStatus::Pending,
        ]));

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

    /**
     * @return array{categories: \Illuminate\Support\Collection<int, TicketCategory>}
     */
    public function with(): array
    {
        return [
            'categories' => TicketCategory::orderBy('name')->get(),
        ];
    }
};
?>

<div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
    @if ($ticket->isTriageRejected())
        <flux:heading size="sm">{{ __('Tu ticket fue rechazado') }}</flux:heading>
        <flux:subheading>{{ __('Revisá el motivo en el chat, corregí lo que haga falta y reenvialo para ser aprobado.') }}</flux:subheading>

        <form wire:submit="save" class="mt-4 flex flex-col gap-5">
            <flux:field>
                <flux:label>{{ __('Título') }}</flux:label>
                <flux:input wire:model="title" />
                <flux:error name="title" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Descripción') }}</flux:label>
                <x-tickets.rich-editor wire:model="description" />
                <flux:error name="description" />
            </flux:field>

            <x-tickets.level-select wire:model.live="importance" :label="__('Importancia')" />

            <x-tickets.level-select wire:model.live="urgency" :label="__('Urgencia')" />

            <x-tickets.level-select wire:model="impact" :label="__('Impacto')" />

            <x-tickets.priority-preview :importance="$importance" :urgency="$urgency" />

            @if ($categories->isNotEmpty())
                <flux:field>
                    <flux:label badge="{{ __('Opcional') }}">{{ __('Categoría') }}</flux:label>
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

            <flux:button type="submit" variant="primary">{{ __('Reenviar para ser aprobado') }}</flux:button>
        </form>
    @else
        <flux:text>{{ __('Ya reenviaste este ticket a aprobación. Un admin lo va a revisar de nuevo.') }}</flux:text>
    @endif
</div>
