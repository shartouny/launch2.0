<?php


namespace App\Traits;


use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;

trait EmailNotification
{
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
    public function sendEmail($subject, $emailContent, $to, $from, $cc, $bcc = '', $messageSubject = ''): bool
    {
        if (config('app.env') != 'production') {
            $localEmail = config('app.local_email_to');
            if (!$localEmail) {
                $this->logger->info("Non Production Environment detected, set an email in LOCAL_EMAIL_TO env to send emails to");
                return false;
            }
            $originalEmail = $to;
            $emailContent = "<P>Originally for $originalEmail</P>" . $emailContent;
            $to = $localEmail;
            $this->logger->info("Non Production Environment detected, sending email to $to");
        }

        $from = 'customerservice@teelaunch.com';

        //--------- Create Email template----------------------//
        $currYear = date('Y');
        $company = 'teelaunch';
        $teelaunchEmail = $from;

        $emailBody = File::get(base_path('public/html/emailTemplate.html'));

        if (!$emailBody) {
            $this->logger->warning("No email body found");
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

        Mail::send([], [], function (Message $message) use ($to, $from, $cc, $bcc, $subject, $emailBody) {
            $message->from($from)
                ->to($to)
                ->subject($subject)
            ->setBody($emailBody, 'text/html');
        });

        $this->logger->info("Send Email | To: $to | From: $from | Subject: $subject");

        return true;
    }

    /**
     * @param $to
     * @param $order
     * @param array $outOfStockMessage
     */
    public function sendOutOfStockEmail($to, $order, $outOfStockMessage)
    {
        $this->logger->debug('order '.$order->id.' is out of stock');
        $this->logger->debug('Sending out of stock email to seller');

        $appUrl = config('app.url');
        $orderUrl = "$appUrl/orders/$order->id";
        $platformOrderNumber = $order->platform_order_number;

        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        $subject = 'The following '.$platformOrderNumber.' contains items that are out of stock';

        $message = 'Due to supply constraints we are unable to fulfill the following item(s)';
        $message .= '<br/>';

        $message .= '<ul>';
        foreach ($outOfStockMessage as $outOfStock){
            $message .= '<li>' .$outOfStock. '</li>';
        }
        $message .= '</ul>';

        $message .= '<br/>';
        $message .= '<a href="' . $orderUrl . '">Check Order</a>';
        $message .= '<br/>';
        $message .= 'Please click on the link above and select a new item variant.';
        $message .= '<br/>';
        $message .= 'teelaunch customer service';

        $this->sendEmail($subject, $message, $to, $from, $cc);
    }

    /**
     * @param $to
     * @param $orderId
     * @param $shipments
     */
    public function sendTrackingNotificationEmail($to, $orderId, $shipments)
    {
        $this->logger->debug('Sending Tracking info to ' . $to);
        $this->logger->debug('Order id ' . $orderId);
        $this->logger->debug('Shipments ' . json_encode($shipments));
        $from = 'customerservice@teelaunch.com';
        $cc = 'support@teelaunch.com';

        $subject = 'Order #'.$orderId.' Tracking';
        $message = '<p>Tracking details</p>';

        foreach ($shipments as $shipment){
            $message .= '<p>Tracking Number: '.$shipment->tracking_number.'<br />';
            $message .= 'Carrier: '.$shipment->carrier.'<br />';
            $message .= 'Method: '.$shipment->method.'<br /><br />';
            $message .= '<a href="'.$shipment->tracking_url.'">Track here</a><br />';
            $message .= '</p>';
        }

        $this->sendEmail($subject, $message, $to, $from, $cc);
    }
}
