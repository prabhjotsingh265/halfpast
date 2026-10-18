<?php

namespace App\Notifications;

use App\Filament\Resources\FailedJobs\FailedJobResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the platform admins a background job gave up after its last try.
 * Sent straight away rather than queued: the queue may be what's broken.
 */
class JobFailedAlert extends Notification
{
    public function __construct(
        public string $job,
        public string $queue,
        public string $error,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = class_basename($this->job);

        return (new MailMessage)
            ->subject("A background job failed: {$name}")
            ->greeting("{$name} failed")
            ->line("It ran out of tries on the {$this->queue} queue. The last error was:")
            ->line('"'.Str::limit(strtok($this->error, "\n") ?: 'No message.', 300).'"')
            ->action('See failed jobs', FailedJobResource::getUrl(panel: 'admin'))
            ->line("It can be tried again from there once the cause is fixed. If {$name} fails again in the next hour, there won't be another email.");
    }
}
