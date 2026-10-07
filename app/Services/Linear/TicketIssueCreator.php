<?php

namespace App\Services\Linear;

use App\Enums\LinearLinkSource;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Create a Linear issue for a ticket that has none, and link the two both ways.
 */
class TicketIssueCreator
{
    /**
     * Each creation costs up to 4 calls against the Linear key's shared hourly budget.
     */
    private const CREATIONS_PER_MINUTE = 5;

    public function __construct(private LinearClient $linear) {}

    /**
     * Returns the new link, or the ticket's existing one if it got linked in the meantime.
     *
     * A lock per ticket keeps two clicks, or two admins, from creating two issues.
     *
     * @throws LinearUnavailableException
     */
    public function create(Ticket $ticket, User $creator): TicketLinearLink
    {
        $link = Cache::lock("linear-issue-create:{$ticket->id}", 30)->get(
            fn () => $ticket->linearLinks()->first() ?? $this->createAndLink($ticket, $creator),
        );

        if ($link === false) {
            throw new LinearUnavailableException('An issue is already being created for this ticket.');
        }

        return $link;
    }

    /**
     * The issue id is kept per ticket until the link is saved, so a retry after a timeout reuses it:
     * Linear rejects the duplicate and the issue it did create is found instead.
     *
     * @throws TooManyLinearIssuesException
     * @throws LinearUnavailableException
     */
    private function createAndLink(Ticket $ticket, User $creator): TicketLinearLink
    {
        $key = "linear-issues:create:{$creator->id}";

        if (RateLimiter::tooManyAttempts($key, self::CREATIONS_PER_MINUTE)) {
            throw new TooManyLinearIssuesException('Too many Linear issues created in the last minute.');
        }

        RateLimiter::hit($key);

        $idKey = "linear-issue-create:{$ticket->id}:issue-id";
        $id = Cache::remember($idKey, now()->addDay(), fn () => (string) Str::uuid());
        $assigneeId = $this->linear->findUserIdByEmail($creator->email);

        try {
            $issue = $this->linear->createIssue(
                id: $id,
                title: "[TK-{$ticket->id}] {$ticket->title}",
                description: $this->description($ticket, $creator),
                priority: $this->priority($ticket->priority),
                assigneeId: $assigneeId,
            );
        } catch (LinearUnavailableException $exception) {
            $issue = $this->createdBehindFailure($id, $idKey) ?? throw $exception;
        }

        try {
            $this->linear->attachUrl($issue->id, $ticket->canonicalUrl(), "TK-{$ticket->id}: {$ticket->title}");
            $source = LinearLinkSource::Attachment;
        } catch (LinearUnavailableException) {
            Log::warning('Could not attach the ticket URL to its new Linear issue.', ['ticket' => $ticket->id, 'issue' => $issue->identifier]);
            $source = LinearLinkSource::Manual;
        }

        $link = $ticket->linkLinearIssue($issue, $source, $creator->id);
        Cache::forget($idKey);

        return $link;
    }

    /**
     * The issue a failed creation may have created after all (e.g. behind a timeout).
     *
     * If it was deleted in Linear since, its id can never be created again, so it is forgotten
     * and the next attempt gets a new one.
     *
     * @throws LinearUnavailableException
     */
    private function createdBehindFailure(string $id, string $idKey): ?LinearIssue
    {
        $issue = $this->linear->findByIds([$id], withTrashed: true)[0] ?? null;

        if ($issue?->trashed) {
            Cache::forget($idKey);

            return null;
        }

        return $issue;
    }

    private function description(Ticket $ticket, User $creator): string
    {
        $text = $this->plainText((string) $ticket->description);

        return implode("\n\n", array_filter([
            __('Ticket: :url', ['url' => $ticket->canonicalUrl()]),
            __('Creado desde la ticketera por :name.', ['name' => $creator->name]),
            $text !== '' ? "---\n\n{$this->fenced($text)}" : null,
        ]));
    }

    /**
     * Client text inside a code block, so Linear renders none of its markdown (remote images, disguised links).
     * The fence is longer than any backtick run in the text, so the text cannot close it early.
     */
    private function fenced(string $text): string
    {
        preg_match_all('/`+/', $text, $runs);
        $fence = str_repeat('`', max([3, ...array_map(fn (string $run): int => strlen($run) + 1, $runs[0])]));

        return "{$fence}\n{$text}\n{$fence}";
    }

    /**
     * The ticket's rich text as plain text that keeps its line breaks, list items and link targets.
     */
    private function plainText(string $html): string
    {
        $html = preg_replace(
            ['~<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>~is', '~<li[^>]*>~i', '~<br\s*/?>|<hr\s*/?>|</(p|li|h[1-6]|pre|blockquote)>~i'],
            ['$2 ($1)', '- ', "\n"],
            $html,
        ) ?? '';

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? '');
    }

    /**
     * Linear's priority scale: 1 urgent, 2 high, 3 medium, 4 low.
     */
    private function priority(TicketPriority $priority): int
    {
        return match ($priority) {
            TicketPriority::Critical => 1,
            TicketPriority::High => 2,
            TicketPriority::Medium => 3,
            TicketPriority::Low => 4,
        };
    }
}
