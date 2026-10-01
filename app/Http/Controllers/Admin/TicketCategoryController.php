<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class TicketCategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', TicketCategory::class);

        return view('admin.categories.index');
    }
}
