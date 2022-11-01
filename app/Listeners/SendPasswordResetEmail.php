<?php

namespace App\Listeners;

use App\Mail\EmailHelper;
use App\Mail\PasswordChanged;
use Illuminate\Mail\Message;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;

class SendPasswordResetEmail
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param object $event
     */
    public function handle($event)
    {
        $user = $event->user;

//        return (new MailMessage)
//            ->to($user->email)
//            ->subject(Lang::get('Password Changed'))
//            ->line(Lang::get('You are receiving this email because your password has been changed.'))
//            ->line(Lang::get('If you did not request the password change then your account may have been compromised.'));

        $resetLink = config('app.url').'/password/forgot';

        $to = $user->email;
        $from = 'customerservice@teelaunch.com';
        $subject = 'Password Changed';
        $emailBody = "<p>You are receiving this email because your password has been changed.</p><p>If you did not request a password change then your account may have been compromised and you should immediately <a href=\"$resetLink\">reset your password</a>.</p>";

        EmailHelper::sendEmail($subject, $emailBody, $to, $from);
    }
}
