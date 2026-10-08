<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

/**
 * Tells the ticket's author that the team needs their reply to continue.
 */
#[DeleteWhenMissingModels]
class TicketAwaitingResponse extends Notification implements ShouldQueue
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
            ->subject(__('[#TK-:id] Esperamos tu respuesta: :title', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->greeting(__('Hola, :name', ['name' => $notifiable->name]))
            ->line(__('El equipo necesita tu respuesta para seguir avanzando con el ticket #TK-:id ":title".', ['id' => $this->ticket->id, 'title' => $this->ticket->title]))
            ->line(__('Entrá al ticket y respondé en la conversación: el ticket vuelve a la cola del equipo en cuanto respondas.'))
            ->action(__('Ver ticket'), route('ticket.show', $this->ticket));
    }
}
