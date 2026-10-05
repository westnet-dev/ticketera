<?php

use App\Services\Linear\LinearClient;
use App\Services\Linear\LinearIssue;
use App\Services\Linear\LinearUnavailableException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    config(['services.linear.key' => 'lin_api_test_key']);
});

test('it finds an issue by identifier, authenticating with the raw API key', function () {
    Http::fake(['api.linear.app/*' => Http::response(['data' => ['issues' => ['nodes' => [linearIssueNode()]]]])]);

    $issue = app(LinearClient::class)->findByIdentifier('ges-911');

    expect($issue)->toBeInstanceOf(LinearIssue::class)
        ->identifier->toBe('GES-911')
        ->stateName->toBe('In Progress')
        ->stateType->toBe('started')
        ->assigneeName->toBe('Ana Pérez');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.linear.app/graphql'
        && $request->header('Authorization') === ['lin_api_test_key']
        && $request['variables']['filter'] === ['team' => ['key' => ['eq' => 'GES']], 'number' => ['eq' => 911]]);
});

test('it returns null when no issue matches the identifier', function () {
    Http::fake(['api.linear.app/*' => Http::response(['data' => ['issues' => ['nodes' => []]]])]);

    expect(app(LinearClient::class)->findByIdentifier('GES-99999'))->toBeNull();
});

test('it lists the issues attached to a url once each', function () {
    $node = linearIssueNode(assignee: null);

    Http::fake(['api.linear.app/*' => Http::response(['data' => ['attachmentsForURL' => ['nodes' => [
        ['issue' => $node],
        ['issue' => $node],
    ]]]])]);

    $issues = app(LinearClient::class)->issuesAttachedToUrl('https://tickets.example.com/tickets/7');

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->assigneeName)->toBeNull();

    Http::assertSent(fn (Request $request) => $request['variables'] === ['url' => 'https://tickets.example.com/tickets/7']);
});

test('lookups include archived issues, which Linear archives on its own once closed', function () {
    fakeLinear();
    $linear = app(LinearClient::class);

    $linear->findByIdentifier('GES-911');
    $linear->issuesAttachedToUrl('https://tickets.example.com/tickets/7');

    Http::assertSentCount(2);
    Http::assertNotSent(fn (Request $request) => ! str_contains($request['query'], 'includeArchived: true'));
});

test('fetching no ids sends no request', function () {
    expect(app(LinearClient::class)->findByIds([]))->toBe([]);

    Http::assertNothingSent();
});

test('it reports Linear as unavailable when the request fails', function (Closure $response) {
    Http::fake(['api.linear.app/*' => $response]);

    app(LinearClient::class)->findByIdentifier('GES-911');
})->with([
    'server error' => fn () => Http::response('Bad gateway', 502),
    'rejected key' => fn () => Http::response(['errors' => [['message' => 'Authentication required']]], 400),
    'graphql error' => fn () => Http::response(['errors' => [['message' => 'Syntax error']], 'data' => null]),
    'connection failure' => fn () => Http::failedConnection(),
])->throws(LinearUnavailableException::class);

test('without an API key it never calls Linear', function () {
    config(['services.linear.key' => null]);

    $linear = app(LinearClient::class);

    expect($linear->isConfigured())->toBeFalse()
        ->and(fn () => $linear->findByIdentifier('GES-911'))->toThrow(LinearUnavailableException::class);

    Http::assertNothingSent();
});

test('it extracts the issue identifier from what an admin pastes', function (string $reference, ?string $identifier) {
    expect(LinearIssue::identifierFrom($reference))->toBe($identifier);
})->with([
    'identifier' => ['GES-911', 'GES-911'],
    'lowercase identifier' => [' ges-911 ', 'GES-911'],
    'issue url' => ['https://linear.app/acme-2/issue/GES-911/vincular-tickets', 'GES-911'],
    'issue url without slug' => ['https://linear.app/acme/issue/GES-911?foo=bar', 'GES-911'],
    'free text' => ['el ticket de facturación', null],
    'non issue url' => ['https://example.com/report-12', null],
]);
