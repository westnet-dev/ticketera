<?php

use App\Enums\LinearLinkSource;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use App\Models\User;
use App\Services\Linear\LinearIssue;

function linearIssue(string $identifier = 'GES-911', string $stateName = 'In Progress', string $stateType = 'started'): LinearIssue
{
    return new LinearIssue(
        id: 'uuid-'.strtolower($identifier),
        identifier: $identifier,
        title: 'Vincular tickets con Linear',
        url: "https://linear.app/acme/issue/{$identifier}",
        stateName: $stateName,
        stateType: $stateType,
        assigneeName: 'Ana Pérez',
    );
}

test('only admins can manage the linear links of a submitted ticket', function () {
    $admin = User::factory()->admin()->create();
    $client = User::factory()->create();
    $ticket = Ticket::factory()->for($client)->create(['status' => 'open']);
    $draft = Ticket::factory()->draft()->for($admin)->create();

    expect($admin->can('manageLinearLinks', $ticket))->toBeTrue()
        ->and($client->can('manageLinearLinks', $ticket))->toBeFalse()
        ->and($admin->can('manageLinearLinks', $draft))->toBeFalse();
});

test('linking a linear issue stores its cached fields and who linked it', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $link = $ticket->linkLinearIssue(linearIssue(), LinearLinkSource::Manual, $admin->id);

    expect($link->fresh())
        ->identifier->toBe('GES-911')
        ->state_name->toBe('In Progress')
        ->assignee_name->toBe('Ana Pérez')
        ->source->toBe(LinearLinkSource::Manual)
        ->linked_by->toBe($admin->id);
});

test('linking the same issue again refreshes it without changing how it was linked', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);
    $ticket->linkLinearIssue(linearIssue(), LinearLinkSource::Manual, $admin->id);

    $ticket->linkLinearIssue(linearIssue(stateName: 'Done', stateType: 'completed'), LinearLinkSource::Attachment);

    $link = TicketLinearLink::sole();

    expect($link)
        ->state_name->toBe('Done')
        ->source->toBe(LinearLinkSource::Manual)
        ->linked_by->toBe($admin->id);
});
