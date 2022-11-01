<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\SlackAttachment;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class SlackFailedJobNotification extends Notification
{
    use Queueable;

    /**
     * @var JobFailed
     */
    public $event;

    /**
     * Create a new notification instance.
     *
     * @param JobFailed $event
     */
    public function __construct($event)
    {
        Log::warning('SlackNotification Event: '.json_encode($event, JSON_PRETTY_PRINT));
        $this->event = $event;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function toSlack($notifiable)
    {
        return (new SlackMessage)
            ->from('Teelaunch App', ':shirt:')
            ->to(config('slack.channel'))
            ->content("Job Failed")
            ->attachment(function (SlackAttachment $attachment) {
                $attachment->title('Job')->content($this->event->job->resolveName());
            })->attachment(function (SlackAttachment $attachment) {
                $attachment->title('Message')->content($this->event->exception->getMessage());
            })->attachment(function (SlackAttachment $attachment) {
                $attachment->title('Exception')->content($this->event->exception->getTraceAsString());
            })->attachment(function (SlackAttachment $attachment) {
                $attachment->title('Payload')->content($this->event->job->getRawBody());
            });
    }
}
