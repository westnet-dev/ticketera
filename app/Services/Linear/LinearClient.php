<?php

namespace App\Services\Linear;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only access to Linear's GraphQL API with a personal API key.
 *
 * Lookups go through the issues() connection instead of issue(id:): a missing issue there
 * comes back as an empty list, not as an error that would have to be told apart from an outage.
 * They include archived issues, since Linear archives closed issues on its own after a while,
 * but leave out deleted ones, which stay in the trash as archived issues for 30 days.
 */
class LinearClient
{
    public const ISSUE_FIELDS = 'id identifier title url trashed state { name type } assignee { name }';

    public function __construct(
        #[Config('services.linear.key')] private ?string $apiKey,
        #[Config('services.linear.url')] private string $url,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * Find an issue by its identifier (e.g. GES-123).
     *
     * @throws LinearUnavailableException
     */
    public function findByIdentifier(string $identifier): ?LinearIssue
    {
        [$teamKey, $number] = explode('-', strtoupper($identifier), 2);

        return $this->issues([
            'team' => ['key' => ['eq' => $teamKey]],
            'number' => ['eq' => (int) $number],
        ], 1)[0] ?? null;
    }

    /**
     * Fetch the current state of several issues by their Linear ids.
     *
     * @param  list<string>  $ids
     * @return list<LinearIssue>
     *
     * @throws LinearUnavailableException
     */
    public function findByIds(array $ids): array
    {
        return $ids === [] ? [] : $this->issues(['id' => ['in' => $ids]], count($ids));
    }

    /**
     * The issues that have a link attachment pointing to the given URL.
     *
     * @return list<LinearIssue>
     *
     * @throws LinearUnavailableException
     */
    public function issuesAttachedToUrl(string $url): array
    {
        $data = $this->query(
            'query AttachedIssues($url: String!) { attachmentsForURL(url: $url, includeArchived: true) { nodes { issue { '.self::ISSUE_FIELDS.' } } } }',
            ['url' => $url],
        );

        return $this->toIssues(array_column((array) data_get($data, 'attachmentsForURL.nodes', []), 'issue'));
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return list<LinearIssue>
     *
     * @throws LinearUnavailableException
     */
    private function issues(array $filter, int $first): array
    {
        $data = $this->query(
            'query Issues($filter: IssueFilter, $first: Int) { issues(filter: $filter, first: $first, includeArchived: true) { nodes { '.self::ISSUE_FIELDS.' } } }',
            ['filter' => $filter, 'first' => $first],
        );

        return $this->toIssues((array) data_get($data, 'issues.nodes', []));
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @return list<LinearIssue>
     */
    private function toIssues(array $nodes): array
    {
        $issues = [];

        foreach ($nodes as $node) {
            if (is_array($node) && ($node['trashed'] ?? false) !== true) {
                $issue = LinearIssue::fromNode($node);
                $issues[$issue->id] = $issue;
            }
        }

        return array_values($issues);
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<array-key, mixed>
     *
     * @throws LinearUnavailableException
     */
    private function query(string $query, array $variables): array
    {
        if (! $this->isConfigured()) {
            throw new LinearUnavailableException('The Linear API key is not configured.');
        }

        try {
            $response = Http::withHeaders(['Authorization' => (string) $this->apiKey])
                ->acceptJson()
                ->timeout(5)
                ->connectTimeout(3)
                ->post($this->url, ['query' => $query, 'variables' => $variables]);
        } catch (ConnectionException $exception) {
            throw new LinearUnavailableException('Linear could not be reached.', previous: $exception);
        }

        $data = $response->json('data');

        if ($response->failed() || ! is_array($data) || $response->json('errors') !== null) {
            Log::warning('Linear API query failed.', [
                'status' => $response->status(),
                'errors' => $response->json('errors.*.message'),
                'codes' => $response->json('errors.*.extensions.code'),
            ]);

            throw new LinearUnavailableException("Linear answered with HTTP {$response->status()}.");
        }

        return $data;
    }
}
