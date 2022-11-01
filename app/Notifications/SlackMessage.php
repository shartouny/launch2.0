<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;

class SlackMessage {

    use Notifiable;

    public $message;

    /**
     * SlackMessage constructor.
     * @param string $message
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    public function routeNotificationForSlack($notification)
    {
        return config('slack.hook_url');
    }
}
