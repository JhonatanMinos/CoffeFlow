<?php

namespace App\Notifications\Workspaces;

use App\Models\WorkspaceInvitation as WorkspaceInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public WorkspaceInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__("You've been invited to join :workspaceName", ['workspaceName' => $workspace->name]))
            ->line(__(':inviterName has invited you to join the :workspaceName workspace.', [
                'inviterName' => $inviter->name,
                'workspaceName' => $workspace->name,
            ]))
            ->line(__('Log in and visit your dashboard to accept or decline this invitation.'))
            ->action(
                __('Log in'),
                route('login', ['invitation' => $this->invitation->code]),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'workspace_id' => $this->invitation->workspace_id,
            'workspace_name' => $this->invitation->workspace->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
