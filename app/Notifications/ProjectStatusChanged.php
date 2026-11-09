<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ProjectStatusChanged extends Notification
{
    use Queueable;

    public Project $project;
    public string $title;
    public string $body;
    public string $url;

    public function __construct(Project $project, string $title, string $body, string $url)
    {
        $this->project = $project;
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->body)
            ->action('View details', $this->url)
            ->line('This is an automated message from ProjectLink.');
    }

    public function toArray($notifiable)
    {
        return [
            'project_id' => $this->project->id,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
