<?php

namespace App\Listeners;

use App\Events\AccountPaymentMethodUpsert;
use App\Models\Orders\Order;
use App\Models\Orders\OrderLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ClearOrdersBillingError
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
     * @param  AccountPaymentMethodUpsert  $event
     * @return void
     */
    public function handle(AccountPaymentMethodUpsert $event)
    {

      $accountId = $event->accountId;

      $orders = Order::whereHas('logs', function ($query){
        return $query->where('message_type', OrderLog::MESSAGE_TYPE_ERROR)->where('message', 'LIKE', '%billing%');
      })->where('has_error', true)
        ->where('account_id', $accountId)
        ->get();

      if($orders){
        $ordersIds = $orders->pluck('id');

        Order::whereIn('id', $ordersIds)->update([
          'has_error' => false
        ]);

        foreach ($ordersIds as $orderId){
          $data[] = array(
            'order_id' => $orderId,
            'message' => 'Errors Cleared',
            'message_type' => OrderLog::MESSAGE_TYPE_INFO,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
          );
        }

        if(!empty($data)){
          OrderLog::insert($data);
        }
      }
    }
}
