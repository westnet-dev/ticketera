<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    /**
     * The requester's listing: one tab per area, with the status filter and
     * the search living in the Livewire list so they stay in the URL.
     */
    public function index(): View
    {
        return view('tickets.index');
    }

    public function create(): View
    {
        return view('tickets.create');
    }

    /**
     * The status pages are now a filter on the listing. Kept as redirects so
     * existing links and bookmarks still land in the right place.
     */
    public function finished(): RedirectResponse
    {
        return redirect()->route('ticket.index', ['status' => 'finished']);
    }

    public function drafts(): RedirectResponse
    {
        return redirect()->route('ticket.index', ['status' => 'draft']);
    }

    public function pendingValidation(): RedirectResponse
    {
        return redirect()->route('ticket.index', ['status' => 'pending_validation']);
    }

    public function show(Ticket $ticket): View
    {
        if ($ticket->isDraft()) {
            Gate::authorize('update', $ticket);

            return view('tickets.draft-edit', ['ticket' => $ticket]);
        }

        Gate::authorize('view', $ticket);

        $ticket->loadMissing(['user', 'createdBy', 'category', 'area']);

        return view('tickets.show', ['ticket' => $ticket]);
    }
}
