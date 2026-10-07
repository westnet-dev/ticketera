<?php

namespace App\Services\Linear;

/**
 * The fields of a Linear issue that a ticket keeps cached on its link.
 */
final readonly class LinearIssue
{
    /**
     * @param  list<string>  $attachmentUrls  The URLs the issue attaches, only present on issue lookups.
     */
    public function __construct(
        public string $id,
        public string $identifier,
        public string $title,
        public string $url,
        public string $stateName,
        public string $stateType,
        public ?string $assigneeName,
        public array $attachmentUrls = [],
        public bool $trashed = false,
    ) {}

    /**
     * Build an issue from a GraphQL node selected with LinearClient::ISSUE_FIELDS.
     *
     * The URL is rendered as a link, so only Linear's own URLs are kept.
     *
     * @param  array<array-key, mixed>  $node
     */
    public static function fromNode(array $node): self
    {
        $assigneeName = data_get($node, 'assignee.name');
        $url = (string) data_get($node, 'url');

        return new self(
            id: (string) data_get($node, 'id'),
            identifier: (string) data_get($node, 'identifier'),
            title: (string) data_get($node, 'title'),
            url: str_starts_with($url, 'https://linear.app/') ? $url : '',
            stateName: (string) data_get($node, 'state.name'),
            stateType: (string) data_get($node, 'state.type'),
            assigneeName: is_string($assigneeName) ? $assigneeName : null,
            attachmentUrls: array_values(array_filter((array) data_get($node, 'attachments.nodes.*.url', []), 'is_string')),
            trashed: data_get($node, 'trashed') === true,
        );
    }

    /**
     * Whether one of the issue's attachments points to the given path, whatever its scheme and host,
     * so a link survives an APP_URL change while Linear still holds the old URL.
     */
    public function attachesPath(string $path): bool
    {
        $path = rtrim($path, '/');

        foreach ($this->attachmentUrls as $url) {
            if (str_ends_with(rtrim((string) parse_url($url, PHP_URL_PATH), '/'), $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The issue identifier (e.g. GES-123) in a pasted identifier or Linear issue URL.
     *
     * In a URL only the /issue/ segment counts: the workspace slug could look like an identifier too.
     */
    public static function identifierFrom(string $reference): ?string
    {
        $reference = trim($reference);

        $pattern = str_contains($reference, '/')
            ? '~/issue/([a-z][a-z0-9]*-\d{1,9})(?:[/?#]|$)~i'
            : '~^([a-z][a-z0-9]*-\d{1,9})$~i';

        return preg_match($pattern, $reference, $matches) === 1 ? strtoupper($matches[1]) : null;
    }

    /**
     * The columns cached on a ticket's link to this issue.
     *
     * @return array{linear_issue_id: string, identifier: string, title: string, url: string, state_name: string, state_type: string, assignee_name: string|null}
     */
    public function toLinkAttributes(): array
    {
        return [
            'linear_issue_id' => $this->id,
            'identifier' => $this->identifier,
            'title' => $this->title,
            'url' => $this->url,
            'state_name' => $this->stateName,
            'state_type' => $this->stateType,
            'assignee_name' => $this->assigneeName,
        ];
    }
}
