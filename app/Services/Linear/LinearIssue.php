<?php

namespace App\Services\Linear;

/**
 * The fields of a Linear issue that a ticket keeps cached on its link.
 */
final readonly class LinearIssue
{
    public function __construct(
        public string $id,
        public string $identifier,
        public string $title,
        public string $url,
        public string $stateName,
        public string $stateType,
        public ?string $assigneeName,
    ) {}

    /**
     * Build an issue from a GraphQL node selected with LinearClient::ISSUE_FIELDS.
     *
     * @param  array<array-key, mixed>  $node
     */
    public static function fromNode(array $node): self
    {
        $assigneeName = data_get($node, 'assignee.name');

        return new self(
            id: (string) data_get($node, 'id'),
            identifier: (string) data_get($node, 'identifier'),
            title: (string) data_get($node, 'title'),
            url: (string) data_get($node, 'url'),
            stateName: (string) data_get($node, 'state.name'),
            stateType: (string) data_get($node, 'state.type'),
            assigneeName: is_string($assigneeName) ? $assigneeName : null,
        );
    }

    /**
     * The issue identifier (e.g. GES-123) in a pasted identifier or Linear issue URL.
     */
    public static function identifierFrom(string $reference): ?string
    {
        $reference = trim($reference);

        // In a URL only the /issue/ segment counts: the workspace slug could look like an identifier too.
        $pattern = str_contains($reference, '/')
            ? '~/issue/([a-z][a-z0-9]*-\d+)(?:[/?#]|$)~i'
            : '~^([a-z][a-z0-9]*-\d+)$~i';

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
