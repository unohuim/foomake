<?php

namespace App\Notifications;

use App\Models\TenantUserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notify an invited tenant member that they can create an account.
 */
class TenantUserInvitationNotification extends Notification
{
    use Queueable;

    /**
     * Create a notification instance.
     */
    public function __construct(
        private readonly TenantUserInvitation $invitation,
        private readonly string $plainToken
    ) {
    }

    /**
     * Get the notification delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('You have been invited')
            ->line('You have been invited to join a team.')
            ->action('Accept invitation', route('invitations.register', $this->plainToken))
            ->line('This invitation expires ' . $this->invitation->expires_at->toDayDateTimeString() . '.');
    }
}
