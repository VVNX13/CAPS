<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Generate a key pair once with:
    |
    |     php artisan push:vapid-keys
    |
    | The command writes both keys into .env. Keep the private key secret.
    |
    */

    'public_key' => env('VAPID_PUBLIC_KEY'),

    'private_key' => env('VAPID_PRIVATE_KEY'),

    /*
    | Contact address push services use to reach you if a subscription breaks.
    | A mailto: or https: URL is required by the spec.
    */
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@tamanteras.id'),

];
