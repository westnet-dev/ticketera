<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * A GraphQL issue node as Linear returns it for LinearClient::ISSUE_FIELDS.
 *
 * @return array<string, mixed>
 */
function linearIssueNode(string $identifier = 'GES-911', string $stateName = 'In Progress', string $stateType = 'started', ?string $assignee = 'Ana Pérez'): array
{
    return [
        'id' => 'uuid-'.strtolower($identifier),
        'identifier' => $identifier,
        'title' => 'Vincular tickets con Linear',
        'url' => "https://linear.app/acme/issue/{$identifier}/vincular-tickets-con-linear",
        'state' => ['name' => $stateName, 'type' => $stateType],
        'assignee' => $assignee === null ? null : ['name' => $assignee],
    ];
}

/**
 * Fake Linear's GraphQL API: issue lookups answer with $issueNodes, URL attachment lookups with $attachedNodes.
 *
 * @param  list<array<string, mixed>>  $issueNodes
 * @param  list<array<string, mixed>>  $attachedNodes
 */
function fakeLinear(array $issueNodes = [], array $attachedNodes = []): void
{
    Http::fake(['api.linear.app/*' => fn (Request $request) => str_contains($request['query'], 'attachmentsForURL')
        ? Http::response(['data' => ['attachmentsForURL' => ['nodes' => array_map(fn (array $node) => ['issue' => $node], $attachedNodes)]]])
        : Http::response(['data' => ['issues' => ['nodes' => $issueNodes]]]),
    ]);
}
