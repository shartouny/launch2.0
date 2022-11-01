<?php

// Hook Receipt
Route::post('/api/v1/orderdesk/hooks/shipments', 'SunriseIntegration\OrderDesk\Http\Controllers\OrderDeskController@handleShipmentsHook')->name('orderdesk.handle-shipments');

Route::post('/api/v1/orderdesk/hooks/order-split', 'SunriseIntegration\OrderDesk\Http\Controllers\OrderDeskController@handleOrderSplitHook')->name('orderdesk.handle-order-split');
