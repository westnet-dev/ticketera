<?php

namespace App\Concerns;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Multi-select status filter shared by the ticket listings.
 *
 * The selection is null until the user touches the filter, which is how a
 * fresh visit (default "En curso") is told apart from a filter the user
 * cleared on purpose (no status restriction).
 */
trait FiltersByTicketStatus
{
    /**
     * @var array<int, string>|null
     */
    #[Url(as: 'statuses')]
    public ?array $statuses = null;

    /**
     * Values outside Ticket::STATUSES the listing offers, keyed by value with their label.
     *
     * @return array<string, string>
     */
    abstract protected function statusFilterExtras(): array;

    /**
     * The status groups the filter shows, keyed by group with its label and statuses.
     *
     * @return array<string, array{label: string, statuses: array<int, string>}>
     */
    public static function statusGroups(): array
    {
        return [
            'ongoing' => ['label' => __('En curso'), 'statuses' => Ticket::ONGOING_STATUSES],
            'finished' => ['label' => __('Finalizados'), 'statuses' => Ticket::FINISHED_STATUSES],
        ];
    }

    public function toggleStatus(string $value): void
    {
        if (! in_array($value, $this->allowedStatusFilterValues(), true)) {
            return;
        }

        $selected = $this->selectedStatuses();

        $this->statuses = in_array($value, $selected, true)
            ? array_values(array_diff($selected, [$value]))
            : [...$selected, $value];

        $this->resetPage();
    }

    /**
     * Tick every status of the group, or untick them all when the group is already full.
     */
    public function toggleStatusGroup(string $group): void
    {
        $groupStatuses = static::statusGroups()[$group]['statuses'] ?? null;

        if ($groupStatuses === null) {
            return;
        }

        $selected = $this->selectedStatuses();

        $this->statuses = array_diff($groupStatuses, $selected) === []
            ? array_values(array_diff($selected, $groupStatuses))
            : array_values(array_unique([...$selected, ...$groupStatuses]));

        $this->resetPage();
    }

    /**
     * The whitelisted selection, falling back to "En curso" on a fresh visit or
     * when the query string brought nothing usable.
     *
     * @return array<int, string>
     */
    protected function selectedStatuses(): array
    {
        if ($this->statuses === null) {
            return Ticket::ONGOING_STATUSES;
        }

        $valid = array_values(array_unique(array_intersect(
            array_filter($this->statuses, 'is_string'),
            $this->allowedStatusFilterValues(),
        )));

        if ($valid === [] && $this->statuses !== []) {
            return Ticket::ONGOING_STATUSES;
        }

        return $valid;
    }

    /**
     * Restrict the query to tickets matching any selected value. An empty
     * selection applies no status restriction at all.
     *
     * @param  Builder<Ticket>  $query
     * @param  array<int, string>  $selected
     */
    protected function applyStatusFilter(Builder $query, User $user, array $selected): void
    {
        if ($selected === []) {
            return;
        }

        $statuses = array_values(array_diff($selected, ['draft', 'pending_validation']));

        // Grouped so the orWhere branches cannot escape the listing's visibility constraints.
        $query->where(function (Builder $query) use ($statuses, $selected, $user): void {
            if ($statuses !== []) {
                $query->orWhereIn('tickets.status', $statuses);
            }

            // Drafts are private: never someone else's, whatever the listing.
            if (in_array('draft', $selected, true)) {
                $query->orWhere(fn (Builder $query) => $query
                    ->where('tickets.status', 'draft')
                    ->where('tickets.user_id', $user->id));
            }

            if (in_array('pending_validation', $selected, true)) {
                $query->orWhere(fn (Builder $query) => $query->pendingValidation());
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function allowedStatusFilterValues(): array
    {
        return [
            ...Ticket::ONGOING_STATUSES,
            ...Ticket::FINISHED_STATUSES,
            ...array_keys($this->statusFilterExtras()),
        ];
    }
}
