<?php

namespace App\Listeners;

class MessageSendingListener
{

    public function __construct()
    {
        //
    }

    public function handle($swiftMessage)
    {
        $swiftMessage->message->getHeaders()->addTextHeader('X-MC-Subaccount', str_replace(' ','', config('mail.mandrill_subaccount', config('app.name', 'Sunrise Integration'))));
    }
}
