<?php

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('a ticket description is sanitized when saved', function () {
    $ticket = Ticket::factory()->create([
        'description' => '<p onclick="steal()">Hola <strong>mundo</strong><script>alert(1)</script></p><p><a href="javascript:alert(1)">link</a></p>',
    ]);

    $stored = DB::table('tickets')->where('id', $ticket->id)->value('description');

    expect($stored)
        ->toContain('<strong>mundo</strong>')
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');
});

test('links keep safe schemes and open in a new tab', function () {
    $ticket = Ticket::factory()->create([
        'description' => '<p><a href="https://example.com">sitio</a></p>',
    ]);

    expect($ticket->fresh()->description)
        ->toContain('href="https://example.com"')
        ->toContain('target="_blank"')
        ->toContain('rel="noopener noreferrer nofollow"');
});

test('a legacy plain text description is escaped into paragraphs on read', function () {
    $ticket = Ticket::factory()->create();

    DB::table('tickets')->where('id', $ticket->id)->update([
        'description' => "Primera línea\nSegunda <script>alert(1)</script>\n\nOtro párrafo",
    ]);

    $description = $ticket->fresh()->description;

    expect($description)
        ->toStartWith('<p>Primera línea<br />Segunda')
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script')
        ->toContain('<p>Otro párrafo</p>');
});

test('legacy html-looking text stored without sanitizing is sanitized on read', function () {
    $ticket = Ticket::factory()->create();

    DB::table('tickets')->where('id', $ticket->id)->update([
        'description' => '<p>Hola</p><img src=x onerror="alert(1)">',
    ]);

    expect($ticket->fresh()->description)->toBe('<p>Hola</p>');
});

test('an empty rich text value is stored as null', function () {
    $ticket = Ticket::factory()->draft()->create(['description' => '<p>   </p>']);

    expect(DB::table('tickets')->where('id', $ticket->id)->value('description'))->toBeNull();
});

test('the ticket page renders the description as html without scripts', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create([
        'description' => '<p>Texto <strong>importante</strong></p><script>alert(1)</script>',
    ]);

    $this->actingAs($client)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('<strong>importante</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('the description length is validated on the visible text, not the markup', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('tickets.create-ticket')
        ->set('title', 'Problema con el servicio')
        ->set('description', '<p><strong>corto</strong></p>')
        ->call('save')
        ->assertHasErrors(['description']);
});

test('chat messages are sent as sanitized rich text and rendered in the conversation', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create();

    $this->actingAs($client);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '<p>Mirá <em>esto</em><script>alert(1)</script></p>')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSeeHtml('<em>esto</em>')
        ->assertSeeHtml('x-data="richEditor(')
        ->assertDontSeeHtml('<script>alert(1)</script>');

    expect(TicketMessage::first()->body)->toBe('<p>Mirá <em>esto</em></p>');
});

test('an empty chat message from the rich editor is rejected', function () {
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create();

    $this->actingAs($client);

    Livewire::test('tickets.ticket-chat', ['ticket' => $ticket])
        ->set('body', '<p></p>')
        ->call('send')
        ->assertHasErrors(['body']);
});
