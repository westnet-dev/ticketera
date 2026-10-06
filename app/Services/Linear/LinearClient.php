<?php

namespace App\Services\Linear;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Access to Linear's GraphQL API with a personal API key: reads, plus creating issues
 * for tickets when a team is configured.
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
        #[Config('services.linear.team_id')] private ?string $teamId = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function canCreateIssues(): bool
    {
        return $this->isConfigured() && filled($this->teamId);
    }

    /**
     * Create an issue in the configured team.
     *
     * The caller picks the issue id, so after a timeout it can look the issue up instead of creating it twice.
     *
     * @param  int  $priority  Linear's scale: 1 urgent to 4 low.
     *
     * @throws LinearUnavailableException
     */
    public function createIssue(string $id, string $title, string $description, int $priority, ?string $assigneeId): LinearIssue
    {
        if (! $this->canCreateIssues()) {
            throw new LinearUnavailableException('No Linear team is configured to create issues in.');
        }

        $data = $this->query(
            'mutation CreateIssue($input: IssueCreateInput!) { issueCreate(input: $input) { success issue { '.self::ISSUE_FIELDS.' } } }',
            ['input' => [
                'id' => $id,
                'teamId' => $this->teamId,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'assigneeId' => $assigneeId,
            ]],
        );

        $node = data_get($data, 'issueCreate.issue');

        if (data_get($data, 'issueCreate.success') !== true || ! is_array($node)) {
            throw new LinearUnavailableException('Linear did not create the issue.');
        }

        return LinearIssue::fromNode($node);
    }

    /**
     * Attach a link to the issue, the same as pasting it with Ctrl+L in Linear.
     *
     * @throws LinearUnavailableException
     */
    public function attachUrl(string $issueId, string $url, string $title): void
    {
        $data = $this->query(
            'mutation AttachUrl($issueId: String!, $url: String!, $title: String) { attachmentLinkURL(issueId: $issueId, url: $url, title: $title) { success } }',
            ['issueId' => $issueId, 'url' => $url, 'title' => $title],
        );

        if (data_get($data, 'attachmentLinkURL.success') !== true) {
            throw new LinearUnavailableException('Linear did not attach the URL.');
        }
    }

    /**
     * The id of the active Linear user with this email, if there is one.
     *
     * @throws LinearUnavailableException
     */
    public function findUserIdByEmail(string $email): ?string
    {
        $data = $this->query(
            'query UserByEmail($email: String!) { users(filter: { email: { eqIgnoreCase: $email }, active: { eq: true } }, first: 1) { nodes { id } } }',
            ['email' => $email],
        );

        $id = data_get($data, 'users.nodes.0.id');

        return is_string($id) ? $id : null;
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
     * Fetch the current state of several issues by their Linear ids, with the URLs they attach.
     *
     * @param  list<string>  $ids
     * @return list<LinearIssue>
     *
     * @throws LinearUnavailableException
     */
    public function findByIds(array $ids): array
    {
        return $ids === [] ? [] : $this->issues(['id' => ['in' => $ids]], count($ids), 'attachments { nodes { url } }');
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
     * @param  string  $extraFields  Selected on top of ISSUE_FIELDS, only by the lookups that use them.
     * @return list<LinearIssue>
     *
     * @throws LinearUnavailableException
     */
    private function issues(array $filter, int $first, string $extraFields = ''): array
    {
        $data = $this->query(
            'query Issues($filter: IssueFilter, $first: Int) { issues(filter: $filter, first: $first, includeArchived: true) { nodes { '.self::ISSUE_FIELDS.' '.$extraFields.' } } }',
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
     * Client errors are caught and rethrown so the request, with the key in its headers, never reaches
     * the error page. A malformed URL logs only the exception class, since its message echoes the URL.
     *
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
        } catch (HttpClientException|InvalidArgumentException $exception) {
            Log::warning('Linear API could not be reached.', [
                'exception' => $exception::class,
                ...($exception instanceof HttpClientException ? ['message' => $exception->getMessage()] : []),
            ]);

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
