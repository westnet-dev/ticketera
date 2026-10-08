<?php

use App\Enums\TriageStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAwaitingResponse;
use App\Notifications\TicketResolved;
use App\Notifications\TicketTriageRejected;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('moving a ticket to awaiting response mails its author', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'awaiting_response');

    Notification::assertSentTo($ticket->user, TicketAwaitingResponse::class, fn ($notification) => $notification->ticket->is($ticket));
    Notification::assertCount(1);
});

test('resolving a ticket asks its author to validate it', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'resolved');

    Notification::assertSentTo($ticket->user, TicketResolved::class, fn ($notification) => $notification->ticket->is($ticket));
    Notification::assertCount(1);
});

test('rejecting in triage from the ticket detail mails the author with the reason', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-triage-actions', ['ticket' => $ticket])
        ->set('rejectionReason', 'Falta más información sobre el problema.')
        ->call('reject')
        ->assertHasNoErrors();

    Notification::assertSentTo(
        $ticket->user,
        TicketTriageRejected::class,
        fn ($notification) => $notification->ticket->is($ticket)
            && str_contains($notification->reason, 'Falta más información sobre el problema.'),
    );
});

test('rejecting in triage from the triage list mails the author with the reason', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.admin-triage-list')
        ->call('startRejecting', $ticket->id)
        ->set('rejectionReason', 'Es un duplicado de otro ticket.')
        ->call('reject')
        ->assertHasNoErrors();

    Notification::assertSentTo(
        $ticket->user,
        TicketTriageRejected::class,
        fn ($notification) => str_contains($notification->reason, 'Es un duplicado de otro ticket.'),
    );
});

test('a ticket filed on behalf of a client mails the client, not the admin who filed it', function () {
    Notification::fake();
    $client = User::factory()->create();
    $filer = User::factory()->admin()->create();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create([
        'user_id' => $client->id,
        'created_by' => $filer->id,
        'status' => 'in_progress',
    ]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'awaiting_response');

    Notification::assertSentTo($client, TicketAwaitingResponse::class);
    Notification::assertNotSentTo($filer, TicketAwaitingResponse::class);
});

test('other status changes send no mail', function (string $status) {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => $status === 'in_progress' ? 'open' : 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', $status);

    expect($ticket->refresh()->status)->toBe($status);
    Notification::assertNothingSent();
})->with(['open', 'in_progress', 'paused', 'pending_deploy', 'cancelled']);

test('approving in triage sends no mail', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['triage_status' => TriageStatus::Pending]);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-triage-actions', ['ticket' => $ticket])
        ->call('approve');

    Notification::assertNothingSent();
});

test('saving a ticket without changing its status sends no mail', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->awaitingResponse()->create();

    $this->actingAs($admin);

    $ticket->update(['title' => 'Un título nuevo para el ticket']);

    Notification::assertNothingSent();
});

test('a deleted author gets no mail and the status still changes', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);
    $ticket->user->delete();

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket->fresh()])
        ->call('updateStatus', 'awaiting_response');

    expect($ticket->refresh()->status)->toBe('awaiting_response');
    Notification::assertNothingSent();
});

test('an admin changing the status of their own ticket gets no mail', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['user_id' => $admin->id, 'status' => 'in_progress']);

    $this->actingAs($admin);

    Livewire::test('tickets.ticket-status-selector', ['ticket' => $ticket])
        ->call('updateStatus', 'resolved');

    Notification::assertNothingSent();
});

test('the notifications are queued', function (string $notification) {
    expect(is_subclass_of($notification, ShouldQueue::class))->toBeTrue();
})->with([TicketAwaitingResponse::class, TicketResolved::class, TicketTriageRejected::class]);

test('the mail is sent once the transaction commits', function () {
    Event::fake([NotificationSent::class]);
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    DB::transaction(fn () => $ticket->update(['status' => 'resolved']));

    Event::assertDispatched(NotificationSent::class, fn (NotificationSent $event) => $event->notification instanceof TicketResolved);
});

test('a rolled back transaction sends no mail', function () {
    Event::fake([NotificationSent::class]);
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'in_progress']);

    $this->actingAs($admin);

    try {
        DB::transaction(function () use ($ticket) {
            $ticket->update(['status' => 'resolved']);

            throw new RuntimeException('Something failed after the update.');
        });
    } catch (RuntimeException) {
        //
    }

    expect($ticket->refresh()->status)->toBe('in_progress');
    Event::assertNotDispatched(NotificationSent::class);
});

test('the mails carry the ticket number, a link to it and Spanish copy', function () {
    $author = User::factory()->create(['name' => 'Ana Ejemplo']);
    $ticket = Ticket::factory()->create(['user_id' => $author->id, 'title' => 'La impresora no imprime']);

    foreach ([new TicketAwaitingResponse($ticket), new TicketResolved($ticket), new TicketTriageRejected($ticket)] as $notification) {
        $mail = $notification->toMail($author);

        expect($mail->subject)->toContain('#TK-'.$ticket->id)->toContain('La impresora no imprime')
            ->and($mail->actionUrl)->toBe(route('ticket.show', $ticket))
            ->and($mail->greeting)->toBe('Hola, Ana Ejemplo');
    }
});

test('the triage rejection mail shows the reason as plain text', function () {
    $author = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $author->id]);

    $mail = (new TicketTriageRejected($ticket, '<p>Falta el <strong>número</strong> de serie &amp; la foto.</p>'))->toMail($author);

    expect($mail->introLines)->toContain('Falta el número de serie & la foto.');
});

test('the triage rejection mail skips the reason section when there is none', function () {
    $author = User::factory()->create();
    $ticket = Ticket::factory()->create(['user_id' => $author->id]);

    $mail = (new TicketTriageRejected($ticket))->toMail($author);

    expect($mail->introLines)->not->toContain('Motivo:');
});
