<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\TicketSetting;
use Illuminate\Contracts\View\View;

class DocumentationController extends Controller
{
    /**
     * Render the user guide, including the live ticket cap so the numbers it quotes stay accurate.
     *
     * A user with several areas gets one usage line per area, since each area
     * has its own budget; a user without areas gets a single line for their own tickets.
     */
    public function index(): View
    {
        $user = auth()->user();
        $areas = $user->areas()->orderBy('title')->get();

        $ticketUsage = $areas->isEmpty()
            ? collect([['area' => null, 'count' => $user->openTicketCountForLimit(null)]])
            : $areas->map(fn (Area $area): array => ['area' => $area->title, 'count' => $user->openTicketCountForLimit($area)]);

        return view('documentation.index', [
            'maxOpenTickets' => TicketSetting::current()->max_open_tickets_per_area,
            'ticketUsage' => $ticketUsage,
        ]);
    }
}
