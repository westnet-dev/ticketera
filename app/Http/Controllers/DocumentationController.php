<?php

namespace App\Http\Controllers;

use App\Models\TicketSetting;
use Illuminate\Contracts\View\View;

class DocumentationController extends Controller
{
    /**
     * Render the user guide, including the live ticket cap so the numbers it quotes stay accurate.
     */
    public function index(): View
    {
        $user = auth()->user();

        return view('documentation.index', [
            'maxOpenTickets' => TicketSetting::current()->max_open_tickets_per_area,
            'openTicketCount' => $user->openTicketCountForLimit(),
            'limitIsPerArea' => $user->ticketLimitIsPerArea(),
        ]);
    }
}
