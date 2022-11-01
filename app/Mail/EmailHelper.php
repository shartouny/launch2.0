<?php

namespace App\Mail;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailHelper {
    /** @noinspection PhpTooManyParametersInspection */

    /**
     * @param $subject
     * @param $emailContent
     * @param $to
     * @param $from
     * @param $cc
     * @param string $bcc
     * @param string $messageSubject
     * @return bool
     */
    static function sendEmail($subject, $emailContent, $to, $from, $cc = '', $bcc = '', $messageSubject = '')
    {
        //--------- Create Email template----------------------//
        $currYear = date('Y');
        $company = 'teelaunch';
        $teelaunchEmail = $from;

        $emailBody = File::get(base_path('public/html/emailTemplate.html'));

        if (!$emailBody) {
            Log::warning("No email body found");
            return false;
        }

        if (empty($messageSubject)) {
            $messageSubject = $subject;
        }

        $emailBody = str_replace('*|MC:SUBJECT|*', $messageSubject, $emailBody);
        $emailBody = str_replace('*|CURRENT_YEAR|*', $currYear, $emailBody);
        $emailBody = str_replace('*|COMPANY|*', $company, $emailBody);
        $emailBody = str_replace('*|HTML:LIST_ADDRESS_HTML|*', $teelaunchEmail, $emailBody);
        $emailBody = str_replace('*|EMAIL_CONTENT|*', $emailContent, $emailBody);
        $emailBody = str_replace('*|EMAIL_SUBJECT|*', $subject, $emailBody);

        //-------------------Send Email --------------------//

        Mail::send([], [], function (Message $message) use ($to, $from, $cc, $bcc, $subject, $emailBody) {
            $message->to($to)
                ->subject($subject)
                ->setBody($emailBody, 'text/html');
        });

        Log::debug("Send Email | To: $to | From: $from | Subject: $subject");

        return true;
    }
}
