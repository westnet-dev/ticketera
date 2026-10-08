<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells the ticket's author it was resolved and asks them to validate it.
 */
#[DeleteWhenMissingModels]
class TicketResolved extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Sent only once the status change is committed, so a rolled-back
     * transaction never reaches anyone's inbox.
     */
    public function __construct(public Ticket $ticket)
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
        return (new MailMessage)
            ->subject(__('[#TK-:id] Ticket resuelto: :title', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->greeting(__('Hola, :name', ['name' => $notifiable->name]))
            ->line(__('El equipo marcó como resuelto el ticket #TK-:id ":title".', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->line(__('Entrá al ticket para confirmar la resolución o, si el problema sigue, rechazarla contándonos qué falta.'))
            ->action(__('Validar resolución'), route('ticket.show', $this->ticket));
    }
}
