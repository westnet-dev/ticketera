<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class TicketController extends Controller
{
    public function index(): View
    {
        return view('tickets.index');
    }

    public function create(): View
    {
        return view('tickets.create');
    }

    public function finished(): View
    {
        return view('tickets.finished');
    }

    public function drafts(): View
    {
        return view('tickets.drafts');
    }

    public function show(Ticket $ticket): View
    {
        if ($ticket->isDraft()) {
            Gate::authorize('update', $ticket);

            return view('tickets.draft-edit', ['ticket' => $ticket]);
        }

        Gate::authorize('view', $ticket);

        return view('tickets.show', ['ticket' => $ticket]);
    }
}
