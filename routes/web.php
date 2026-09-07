<?php

use App\Http\Controllers\Admin\AreaController as AdminAreaController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\TriageController as AdminTriageController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

/* SOLICITANTES DE TICKETS (cliente o admin haciendo un pedido propio) */
Route::middleware(['auth', 'role:client,admin'])->prefix('tickets')->group(function () {
    Route::get('/', [TicketController::class, 'index'])->name('ticket.index');
    Route::get('/create', [TicketController::class, 'create'])->name('ticket.create');
    Route::get('/finished', [TicketController::class, 'finished'])->name('ticket.finished');
    Route::get('/drafts', [TicketController::class, 'drafts'])->name('ticket.drafts');
});

/* TICKET DETAIL (shared across roles, authorized via TicketPolicy::view) */
Route::middleware(['auth'])->prefix('tickets')->group(function () {
    Route::get('/{ticket}', [TicketController::class, 'show'])->name('ticket.show');
});

/* ADMIN */
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/tickets', [AdminTicketController::class, 'index'])->name('admin.tickets');
    Route::get('/triage', [AdminTriageController::class, 'index'])->name('admin.triage');
    Route::get('/users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('/areas', [AdminAreaController::class, 'index'])->name('admin.areas');
});

require __DIR__.'/settings.php';
