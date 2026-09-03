<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class AreaController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Area::class);

        return view('admin.areas.index');
    }
}
