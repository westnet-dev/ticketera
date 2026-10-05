<?php

use App\Enums\LinearLinkSource;
use App\Models\Ticket;
use App\Services\Linear\LinearClient;
use App\Services\Linear\LinearIssue;
use App\Services\Linear\LinearUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    /**
     * How often opening the ticket refreshes its links from Linear, to stay well under the API rate limit.
     */
    private const SYNC_EVERY_MINUTES = 10;

    public Ticket $ticket;

    public string $reference = '';

    public bool $linearUnavailable = false;

    public function mount(LinearClient $linear): void
    {
        Gate::authorize('manageLinearLinks', $this->ticket);

        if (Cache::add($this->syncCacheKey(), true, now()->addMinutes(self::SYNC_EVERY_MINUTES))) {
            $this->sync($linear);
        }
    }

    public function placeholder(): string
    {
        return '<p class="p-4 text-xs text-neutral-500 dark:text-neutral-400">'.e(__('Consultando Linear...')).'</p>';
    }

    public function link(LinearClient $linear): void
    {
        Gate::authorize('manageLinearLinks', $this->ticket);

        $this->validate(['reference' => ['required', 'string', 'max:2048']]);

        $identifier = LinearIssue::identifierFrom($this->reference);

        if ($identifier === null) {
            $this->addError('reference', __('Ingresá un identificador como GES-123 o la URL del issue.'));

            return;
        }

        try {
            $issue = $linear->findByIdentifier($identifier);
        } catch (LinearUnavailableException) {
            $this->addError('reference', __('No se pudo consultar Linear. Probá de nuevo en unos minutos.'));

            return;
        }

        if ($issue === null) {
            $this->addError('reference', __('No se encontró :identifier en Linear.', ['identifier' => $identifier]));

            return;
        }

        $this->ticket->linkLinearIssue($issue, LinearLinkSource::Manual, auth()->id());

        $this->reset('reference');
    }

    /**
     * Only manual links can be removed: a link detected from Linear would come back on the next sync.
     */
    public function unlink(int $linkId): void
    {
        Gate::authorize('manageLinearLinks', $this->ticket);

        $this->ticket->linearLinks()
            ->whereKey($linkId)
            ->where('source', LinearLinkSource::Manual)
            ->delete();
    }

    public function refresh(LinearClient $linear): void
    {
        Gate::authorize('manageLinearLinks', $this->ticket);

        Cache::put($this->syncCacheKey(), true, now()->addMinutes(self::SYNC_EVERY_MINUTES));

        $this->sync($linear);
    }

    public function with(): array
    {
        return [
            'links' => $this->ticket->linearLinks()->get(),
        ];
    }

    /**
     * Refresh the cached state of the existing links and pick up issues that attach this ticket's URL.
     */
    private function sync(LinearClient $linear): void
    {
        if (! $linear->isConfigured()) {
            return;
        }

        try {
            $links = $this->ticket->linearLinks()->get();
            $issues = collect($linear->findByIds($links->pluck('linear_issue_id')->all()))->keyBy('id');

            foreach ($links as $link) {
                $issue = $issues->get($link->linear_issue_id);

                if ($issue !== null) {
                    $link->refreshFrom($issue);
                }
            }

            foreach ($linear->issuesAttachedToUrl($this->ticket->canonicalUrl()) as $issue) {
                $this->ticket->linkLinearIssue($issue, LinearLinkSource::Attachment);
            }

            $this->linearUnavailable = false;
        } catch (LinearUnavailableException) {
            $this->linearUnavailable = true;

            // Let the next page view try again instead of waiting out the interval.
            Cache::forget($this->syncCacheKey());
        }
    }

    private function syncCacheKey(): string
    {
        return "linear-links:synced:{$this->ticket->id}";
    }
};
?>

<div class="flex flex-col gap-3 p-4 text-sm">
    @if ($linearUnavailable)
        <flux:callout icon="exclamation-triangle" variant="warning">
            <flux:callout.text>{{ __('No se pudo consultar Linear. Se muestra el último estado conocido.') }}</flux:callout.text>
        </flux:callout>
    @endif

    @forelse ($links as $link)
        <div wire:key="linear-link-{{ $link->id }}" class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-neutral-900 hover:underline dark:text-white">
                    {{ $link->identifier }}
                </a>
                <p class="wrap-break-word text-xs text-neutral-600 dark:text-neutral-300">{{ $link->title }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                    <flux:badge size="sm" :color="$link->stateColor()">{{ $link->state_name }}</flux:badge>
                    <span>{{ $link->assignee_name ?? __('Sin asignar') }}</span>
                    @if ($link->wasDetectedFromLinear())
                        <span>· {{ __('Vinculado desde Linear') }}</span>
                    @endif
                </div>
            </div>
            @unless ($link->wasDetectedFromLinear())
                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="unlink({{ $link->id }})" :aria-label="__('Desvincular :identifier', ['identifier' => $link->identifier])" />
            @endunless
        </div>
    @empty
        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Sin issues de Linear vinculados.') }}</p>
    @endforelse

    <form wire:submit="link" class="flex flex-col gap-2 border-t border-neutral-200 pt-3 dark:border-neutral-700">
        <flux:field>
            <div class="flex items-start gap-2">
                <flux:input size="sm" wire:model="reference" :placeholder="__('GES-123 o URL del issue')" class="flex-1" />
                <flux:button size="sm" type="submit">{{ __('Vincular') }}</flux:button>
            </div>
            <flux:error name="reference" />
        </flux:field>
    </form>

    <div class="flex flex-col gap-1 text-xs text-neutral-500 dark:text-neutral-400">
        <p>{{ __('Para vincularlo desde Linear, agregá este link al issue con Ctrl+L:') }}</p>
        <code class="break-all">{{ $ticket->canonicalUrl() }}</code>
        <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="refresh" class="self-start">
            {{ __('Actualizar desde Linear') }}
        </flux:button>
    </div>
</div>
