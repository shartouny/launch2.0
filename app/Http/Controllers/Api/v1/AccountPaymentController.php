<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Accounts\AccountPayment;
use App\Http\Resources\Accounts\AccountPaymentResource;
use App\Http\Resources\Accounts\AccountPaymentCollectionResource;
use App\Models\Accounts\Account;
use App\Models\Orders\OrderAccountPayment;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * @group  Account
 *
 * APIs for managing account
 */

class AccountPaymentController extends Controller
{
    /**
     * Get Payment History
     *
     * Get all account payment history
     * 
     * @queryParam  limit paging limit
     */
    public function index(Request $request)
    {
        try {
            $limit = (int)config('pagination.per_page');

            if ($request->has('limit')) {
                $limit = $request->limit;
            }

            $paymentHistory = AccountPayment::with(['accountPaymentMethod' => function ($query) {
                return $query->with('metadata')->with('paymentMethod');
            }])->orderBy('created_at', 'desc')->paginate($limit);
;
            return new AccountPaymentCollectionResource($paymentHistory);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->responseServerError();
        }
    }

    /**
     * Get Payment History By Id
     *
     * Get account payment history by id
     * 
     * @urlParam  payment_history  required Payment history id 
     */
    public function show($id)
    {
        try {
            $paymentHistory = AccountPayment::with('orderPayments', 'orderPayments.lineItems', 'orderPayments.order', 'orderPayments.order.store.platform')->where('id', $id)->first();
            return new AccountPaymentResource($paymentHistory);
        } catch (\Exception $exception) {
            Log::error($exception);
            return $this->responseServerError($exception->getMessage());
        }
    }

    public function invoices()
    {
        $invoices = DB::table('account_payments')
            ->selectRaw("min(id) as 'id', concat(monthname(created_at), ', ', year(created_at)) as period, sum(amount) as 'amount'")
            ->where('account_id', Auth::user()->account_id)
            ->whereRaw("concat(monthname(created_at), ', ', year(created_at)) <> concat(monthname(now()), ', ', year(now()))")
            ->groupBy('period')
            ->orderBy('id', 'desc')
            ->paginate(config('pagination.per_page'));

        if (!$invoices) {
            return response()->json('Something went wrong', 500);
        }

        return response()->json($invoices);
    }

    public function downloadPDF(Request $request)
    {
        $shippingLabel = Account::first()->shippingLabel ?? [];
        $billingAddress = $shippingLabel->billingAddress ?? [];

        $orders = OrderAccountPayment::whereHas('accountPayment', function ($query) {
                return $query->where('account_id', Auth::user()->account_id);
            })->whereRaw("CONCAT(MONTHNAME(created_at),', ', YEAR(created_at)) like '%$request->date%'")
            ->get();

        $totals = collect([
            'lineItemsTotal' => 0,
            'shippingTotal' => 0,
            'taxTotal' => 0,
            'discountTotal' => 0,
            'totalCost' => 0,
            'refund' => 0
        ]);

        $orders->map(function ($order) use ($totals) {
            $totals->put('lineItemsTotal', $totals->get('lineItemsTotal') + $order->line_item_subtotal);
            $totals->put('shippingTotal', $totals->get('shippingTotal') + $order->shipping_subtotal);
            $totals->put('taxTotal', $totals->get('taxTotal') + $order->tax);
            $totals->put('discountTotal', $totals->get('discountTotal') + $order->discount);
            $totals->put('totalCost', $totals->get('totalCost') + $order->total_cost);
            $totals->put('refund', $totals->get('refund') + $order->refund);
        });

        $pdf = App::make('dompdf.wrapper');
        $pdf->loadView('/billing/invoice/invoice', [
            'period' => $request->date,
            'orders' => $orders,
            'totals' => (object)$totals->all(),
            'billingAddress' => $billingAddress,
            'vat' => $shippingLabel->vat,
            'invoiceNumber' => $request->invoiceNumber
        ]);
        return $pdf->stream('invoice.pdf');
    }
}
