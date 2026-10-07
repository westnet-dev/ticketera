<?php

namespace App\Services\Linear;

use App\Enums\LinearLinkSource;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\TicketLinearLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Create a Linear issue for a ticket that has none, and link the two both ways.
 */
class TicketIssueCreator
{
    public function __construct(private LinearClient $linear) {}

    /**
     * Returns the new link, or the ticket's existing one if it got linked in the meantime.
     *
     * @throws LinearUnavailableException
     */
    public function create(Ticket $ticket, User $creator): TicketLinearLink
    {
        // Two clicks, or two admins, on the same ticket must not create two issues.
        $link = Cache::lock("linear-issue-create:{$ticket->id}", 30)->get(
            fn () => $ticket->linearLinks()->first() ?? $this->createAndLink($ticket, $creator),
        );

        if ($link === false) {
            throw new LinearUnavailableException('An issue is already being created for this ticket.');
        }

        return $link;
    }

    /**
     * @throws LinearUnavailableException
     */
    private function createAndLink(Ticket $ticket, User $creator): TicketLinearLink
    {
        // A retry reuses the id, so an issue created behind a timeout fails as a duplicate and is found below.
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
            // A timeout can hide an issue that was created after all.
            $issue = $this->linear->findByIds([$id])[0] ?? throw $exception;
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
