<?php

return [

    /*
    |--------------------------------------------------------------------------
    | QRIS payment auto-confirmation
    |--------------------------------------------------------------------------
    |
    | How often the background job asks the gateway about orders that are still
    | marked as unpaid. Sixty seconds keeps the kitchen queue current without
    | hammering the gateway.
    |
    */

    'sync_interval_seconds' => (int) env('QRIS_SYNC_INTERVAL_SECONDS', 60),

    /*
    | How far back to look for unpaid orders. QRIS-ify orders expire after 15
    | minutes, so anything older is left for the cashier to handle manually.
    */
    'sync_window_minutes' => (int) env('QRIS_SYNC_WINDOW_MINUTES', 30),

];
