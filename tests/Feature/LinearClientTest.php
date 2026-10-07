<?php

use App\Services\Linear\LinearClient;
use App\Services\Linear\LinearIssue;
use App\Services\Linear\LinearUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    $linear->findByIds(['uuid-ges-911']);
    $linear->issuesAttachedToUrl('https://tickets.example.com/tickets/7');

    Http::assertSentCount(3);
    Http::assertNotSent(fn (Request $request) => ! str_contains($request['query'], 'includeArchived: true'));
});

test('it fetches issues by their linear ids', function () {
    fakeLinear(issueNodes: [linearIssueNode('GES-911'), linearIssueNode('GES-120'), linearIssueNode('GES-7')]);

    $issues = app(LinearClient::class)->findByIds(['uuid-ges-911', 'uuid-ges-120']);

    expect(array_column($issues, 'identifier'))->toBe(['GES-911', 'GES-120']);

    Http::assertSent(fn (Request $request) => $request['variables'] === ['filter' => ['id' => ['in' => ['uuid-ges-911', 'uuid-ges-120']]], 'first' => 2]);
});

test('it leaves out issues deleted in linear, which stay in the trash as archived', function () {
    $trashed = [...linearIssueNode('GES-120'), 'trashed' => true];

    fakeLinear(issueNodes: [$trashed], attachedNodes: [$trashed, linearIssueNode('GES-911')]);
    $linear = app(LinearClient::class);

    expect($linear->findByIds(['uuid-ges-120']))->toBe([])
        ->and(array_column($linear->issuesAttachedToUrl('https://tickets.example.com/tickets/7'), 'identifier'))->toBe(['GES-911']);
});

test('issue lookups read the urls each issue attaches', function () {
    fakeLinear(issueNodes: [[...linearIssueNode(), 'attachments' => ['nodes' => [
        ['url' => 'https://tickets.example.com/tickets/7'],
        ['url' => null],
    ]]]]);

    $issues = app(LinearClient::class)->findByIds(['uuid-ges-911']);

    expect($issues[0]->attachmentUrls)->toBe(['https://tickets.example.com/tickets/7'])
        ->and(LinearIssue::fromNode(linearIssueNode())->attachmentUrls)->toBe([]);

    Http::assertSent(fn (Request $request) => str_contains($request['query'], 'attachments { nodes { url } }'));
});

test('an issue attaches a ticket path whatever the scheme, host or trailing slash of the url', function () {
    $issue = LinearIssue::fromNode([...linearIssueNode(), 'attachments' => ['nodes' => [['url' => 'http://old.example.com/tickets/7/']]]]);

    expect($issue->attachesPath('/tickets/7'))->toBeTrue()
        ->and($issue->attachesPath('/tickets/17'))->toBeFalse()
        ->and($issue->attachesPath('/tickets/77'))->toBeFalse();
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

test('a connection failure is logged without the API key', function () {
    Log::spy();
    Http::fake(['api.linear.app/*' => Http::failedConnection()]);

    expect(fn () => app(LinearClient::class)->findByIdentifier('GES-911'))->toThrow(LinearUnavailableException::class);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $message === 'Linear API could not be reached.'
        && $context['exception'] === ConnectionException::class
        && ! str_contains(json_encode($context), 'lin_api_test_key'));
});

test('a malformed API url is reported as Linear being unavailable', function () {
    config(['services.linear.url' => 'https://api.linear app/graphql']);

    app(LinearClient::class)->findByIdentifier('GES-911');
})->throws(LinearUnavailableException::class);

test('it only keeps issue urls that point to linear', function (string $url, string $kept) {
    expect(LinearIssue::fromNode([...linearIssueNode(), 'url' => $url])->url)->toBe($kept);
})->with([
    'linear url' => ['https://linear.app/acme/issue/GES-911', 'https://linear.app/acme/issue/GES-911'],
    'script url' => ['javascript:alert(1)', ''],
    'other host' => ['https://linear.app.example.com/issue/GES-911', ''],
]);

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
    'number beyond linear range' => ['GES-99999999999999999999', null],
]);
