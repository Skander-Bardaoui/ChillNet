<?php

namespace App\Notifications;

use App\Models\Signalement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignalementNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Signalement $signalement,
        private readonly string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->message())
            ->action('Voir mes signalements', route('signalements.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'signalement_id' => $this->signalement->id,
            'event' => $this->event,
            'title' => $this->title(),
            'message' => $this->message(),
            'statut' => $this->signalement->statut,
        ];
    }

    private function title(): string
    {
        return $this->event === 'created' ? 'Signalement envoyé' : 'Statut du signalement mis à jour';
    }

    private function message(): string
    {
        return $this->event === 'created'
            ? 'Votre signalement #'.$this->signalement->id.' a bien été enregistré.'
            : 'Le signalement #'.$this->signalement->id.' est maintenant « '.str_replace('_', ' ', $this->signalement->statut).' ».';
    }
}