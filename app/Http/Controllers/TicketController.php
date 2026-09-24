<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
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
     * Render one of the ticket list tabs, all of which share the filter bar.
     */
    private function listView(string $view): View
    {
        return view($view, [
            'pendingValidationCount' => Ticket::query()
                ->forUsers([auth()->id()])
                ->pendingValidation()
                ->count(),
        ]);
    }
}
