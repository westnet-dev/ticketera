<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells the ticket's author it was rejected in triage, and why.
 */
#[DeleteWhenMissingModels]
class TicketTriageRejected extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The reason travels with the notification rather than being read by the
     * worker: by the time the job runs the author may have replied, and the
     * ticket's latest message would no longer be the reason.
     */
    public function __construct(public Ticket $ticket, public ?string $reason = null)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('[#TK-:id] Ticket rechazado en triage: :title', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->greeting(__('Hola, :name', ['name' => $notifiable->name]))
            ->line(__('El equipo revisó el ticket #TK-:id ":title" y lo rechazó en triage.', ['id' => $this->ticket->id, 'title' => $this->ticket->title]));

        $reason = $this->plainTextReason();

        if ($reason !== '') {
            $message->line(__('Motivo:'))->line($reason);
        }

        return $message
            ->line(__('Podés corregir el ticket y volver a enviarlo desde su detalle.'))
            ->action(__('Ver ticket'), route('ticket.show', $this->ticket));
    }

    /**
     * The reason is stored as sanitized rich text; the mail shows it as plain
     * text, and MailMessage escapes it on output.
     */
    private function plainTextReason(): string
    {
        return trim(html_entity_decode(strip_tags((string) $this->reason), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
