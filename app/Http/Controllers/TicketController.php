<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(): View
    {
        return $this->listView('tickets.index');
    }

    public function create(): View
    {
        return view('tickets.create');
    }

    public function finished(): View
    {
        return $this->listView('tickets.finished');
    }

    public function drafts(): View
    {
        return $this->listView('tickets.drafts');
    }

    public function pendingValidation(): View
    {
        return $this->listView('tickets.pending-validation');
    }

    public function show(Ticket $ticket): View
    {
        if ($ticket->isDraft()) {
            Gate::authorize('update', $ticket);

            return view('tickets.draft-edit', ['ticket' => $ticket]);
        }

        Gate::authorize('view', $ticket);

        $ticket->loadMissing(['user', 'createdBy']);

        return view('tickets.show', ['ticket' => $ticket]);
    }

    /**
     * Render one of the ticket list tabs, all of which share the header,
     * the summary cards and the filter bar.
     */
    private function listView(string $view): View
    {
        $user = auth()->user();

        $pendingValidationCount = Ticket::query()
            ->forUsers([$user->id])
            ->pendingValidation()
            ->count();

        return view($view, [
            'pendingValidationCount' => $pendingValidationCount,
            'ticketCounts' => $this->ticketCounts($user, $pendingValidationCount),
        ]);
    }

    /**
     * @return array{total: int, ongoing: int, finished: int, drafts: int, pending_validation: int, assigned_to_me: int, created_this_week: int}
     */
    private function ticketCounts(User $user, int $pendingValidationCount): array
    {
        $countsByStatus = Ticket::query()
            ->listedFor($user)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $countFor = fn (array $statuses): int => (int) $countsByStatus->only($statuses)->sum();

        return [
            'total' => $countFor(['open', 'in_progress', 'paused', 'resolved', 'cancelled']),
            'ongoing' => $countFor(['open', 'in_progress', 'paused']),
            'finished' => $countFor(['resolved', 'cancelled']),
            'drafts' => $countFor(['draft']),
            'pending_validation' => $pendingValidationCount,
            'assigned_to_me' => $user->isAdmin()
                ? Ticket::query()->ongoing()->where('assigned_to', $user->id)->count()
                : 0,
            'created_this_week' => Ticket::query()
                ->listedFor($user)
                ->where('status', '!=', 'draft')
                ->where('created_at', '>=', now()->startOfWeek())
                ->count(),
        ];
    }
}
