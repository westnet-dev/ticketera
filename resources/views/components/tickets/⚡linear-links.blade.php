<?php

use App\Enums\LinearLinkSource;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use App\Services\Linear\LinearClient;
use App\Services\Linear\LinearIssue;
use App\Services\Linear\LinearUnavailableException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    /**
     * How often opening the ticket refreshes its links from Linear, to stay well under the API rate limit.
     */
    private const SYNC_EVERY_MINUTES = 10;

    /**
     * Actions that call Linear per admin and minute: one request can batch many calls, and the API rate limit is shared by the key.
     */
    private const ACTIONS_PER_MINUTE = 20;

    public Ticket $ticket;

    public string $reference = '';

    #[Locked]
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

        if ($this->throttled()) {
            $this->addError('reference', __('Demasiados intentos. Probá de nuevo en un minuto.'));

            return;
        }

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
     * Only manual links can be removed: a link detected from Linear follows its attachment there.
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

        if ($this->throttled()) {
            return;
        }

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

            $attached = $linear->issuesAttachedToUrl($this->ticket->canonicalUrl());

            foreach ($attached as $issue) {
                $this->ticket->linkLinearIssue($issue, LinearLinkSource::Attachment);
            }

            $this->removeDetachedLinks($links, $issues, array_column($attached, 'id'));

            $this->linearUnavailable = false;
        } catch (LinearUnavailableException) {
            $this->linearUnavailable = true;

            // Retry soon instead of waiting out the interval, without calling Linear on every view.
            Cache::put($this->syncCacheKey(), true, now()->addMinute());
        }
    }

    /**
     * Delete the detected links whose issue was deleted in Linear or no longer attaches this ticket.
     * The attachment is matched by path, not by canonicalUrl(): after an APP_URL change Linear
     * still holds the old URL, and matching the new one would drop every detected link.
     *
     * @param  Collection<int, TicketLinearLink>  $links
     * @param  BaseCollection<string, LinearIssue>  $issues
     * @param  list<string>  $attachedIds
     */
    private function removeDetachedLinks(Collection $links, BaseCollection $issues, array $attachedIds): void
    {
        $ticketPath = route('ticket.show', $this->ticket, absolute: false);

        $detachedIds = $links
            ->filter(fn (TicketLinearLink $link) => $link->wasDetectedFromLinear())
            ->reject(fn (TicketLinearLink $link) => in_array($link->linear_issue_id, $attachedIds, true)
                || $issues->get($link->linear_issue_id)?->attachesPath($ticketPath))
            ->modelKeys();

        $this->ticket->linearLinks()->whereKey($detachedIds)->delete();
    }

    private function throttled(): bool
    {
        $key = 'linear-links:actions:'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, self::ACTIONS_PER_MINUTE)) {
            return true;
        }

        RateLimiter::hit($key);

        return false;
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
                {{-- A deleted issue, or one the key lost access to, stops refreshing; the age makes that visible. --}}
                <p class="mt-0.5 text-xs text-neutral-400">{{ __('Actualizado :time', ['time' => $link->synced_at->diffForHumans()]) }}</p>
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
        {{-- Linear matches the attached URL exactly: a trailing slash or another host is not detected. --}}
        <p>{{ __('Para vincularlo desde Linear, copiá este link y agregalo al issue con Ctrl+L:') }}</p>
        <flux:input size="sm" readonly copyable :value="$ticket->canonicalUrl()" :aria-label="__('Link del ticket')" />
        <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="refresh" class="self-start">
            {{ __('Actualizar desde Linear') }}
        </flux:button>
    </div>
</div>
