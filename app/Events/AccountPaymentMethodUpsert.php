<?php

namespace App\Events;

use App\Models\Accounts\AccountPaymentMethod;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountPaymentMethodUpsert
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $accountId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(AccountPaymentMethod $accountPaymentMethod)
    {
        $this->accountId = $accountPaymentMethod->account_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('OrdersBillingErrorChannel');
    }
}
