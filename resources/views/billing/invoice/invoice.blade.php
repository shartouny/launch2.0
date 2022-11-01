<style>
    .wrapper {
        font-family: 'sans-serif';
        color: #2B3377;
        padding: 10px;
    }
    .address {
        margin-top: 10px;
        width: 300px;
    }
    h1 {
        margin-top: 20px;
    }
    .bill-header::after {
        content: '';
        position: relative;
        display: block;
        border: 1px solid #ccc;
        width: 200px;
        margin-top: 5px;
    }
    .billing-address {
        max-width: 250px;
    }
    .info-table {
        width: 100%;
        color: inherit;
    }
    .additional-info-table {
        color: inherit;
        width: 300px;
        margin: 30px 0 auto auto;
    }
    .orders-table {
        color: #2b3377;
        width: 100%;
        text-align: center;
        margin-top: 40px;
    }
    .orders-table thead {
        background: #E4E6F4;
        height: 50px;
    }
    .orders-table th {
        padding: 15px 0;
    }
    .orders-table td {
        padding: 20px;
        border-bottom: 1px solid #ccc;
    }
    .totals-table {
        width: 100%;
        margin-top: 40px;
        color: inherit;
    }
    .totals-table tr td {
        padding-top: 5px;
    }
    .tatals-table tr td:first-child {
        width: 50%;
    }
    .label {
        font-weight: bold;
        width: 200px;
        padding-top: 5px;
        display: inline-block;
    }
    .price {
        margin-left: 30px;
        font-weight: normal;
    }
    .totals-table .row {
        width: 350px;
        font-weight: bold;
        border-bottom: 1px solid #ccc;
    }
    .last {
        border-bottom: none !important;
        padding-top: 15px !important;
    }
    img {
        fill: green;
    }

</style>

<div class="wrapper">
    <img src="{{ asset('/teelaunch-logo.png') }}" />
    <p class="address">808 N West Ave, Sioux Falls, SD 57104, US</p>

    <h1>INVOICE SUMMARY</h1>
    <table class="info-table">
        <tr>
            <td>
                <p class="bill-header">Bill to</p>
                <p class="billing-address">
                    {{ $billingAddress->address1 ?? $billingAddress->address1 . ', ' }}
                    {{ $billingAddress->address2 ?? $billingAddress->address2 . ', ' }}
                    {{ $billingAddress->state ?? $billingAddress->state . ', ' }}
                    {{ $billingAddress->zip ?? $billingAddress->zip . ', ' }}
                    {{ $billingAddress->city ?? $billingAddress->city . ', ' }}
                    {{ $billingAddress->country ?? $billingAddress->country . ' '}}
                </p>
            </td>
            <td>
                <table class="additional-info-table">
                    <tr>
                        <th>Period</th>
                        <td>{{ $period }}</td>
                    </tr>
                    <tr>
                        <th>Invoice #</th>
                        <td>{{ $invoiceNumber }}</td>
                    </tr>
                    @if($vat)
                    <tr>
                        <th>VAT ID</th>
                        <td>{{ $vat }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
    <table class="orders-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Product Total</th>
                <th>Shipping</th>
                <th>Tax</th>
                <th>Discount</th>
                <th>Sub Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $order)
            <tr>
                <td>{{ $order->order_id }}</td>
                <td>{{ $order->line_item_subtotal }}</td>
                <td>{{ $order->shipping_subtotal }}</td>
                <td>{{ $order->tax }}</td>
                <td>{{ $order->discount }}</td>
                <td>{{ number_format($order->total_cost - $order->refund, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals-table">
        <tr>
            <td></td>
            <td class="row">
                <span class="label">Product Total</span>
                <span class="price">${{ number_format($totals->lineItemsTotal, 2) }}</span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class="row">
                <span class="label">Shipping Fees</span>
                <span class="price">${{ number_format($totals->shippingTotal, 2) }}</span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class="row">
                <span class="label">Sales Tax</span>
                <span class="price">${{ number_format($totals->taxTotal, 2) }}</span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class="row">
                <span class="label">Discount</span>
                <span class="price">${{ number_format($totals->discountTotal, 2) }}</span>
            </td>
        </tr>
        <tr>
            <td></td>
            <td class="price last">
                <span class="label">Total</span>
                <span class="price">$ {{ number_format($totals->totalCost - $totals->refund, 2) }}</span>
            </td>
        </tr>
    </table>
</div>
