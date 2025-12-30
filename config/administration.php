<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | This value determines the minimum quantity threshold for stock alerts.
    | When a product's stock falls below this number, an alert will be sent.
    |
    */

    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Admin Email for Stock Alerts
    |--------------------------------------------------------------------------
    |
    | This is the email address that will receive low stock alert notifications.
    |
    */

    'admin_email' => env('STOCK_ADMIN_EMAIL', 'admin@shoppingcart.com'),

];

