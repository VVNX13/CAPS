<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\QrisHelper;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        // Merchant QRIS string used to build dynamic (per-amount) QR codes.
        Setting::put('qris_static_payload', QrisHelper::DEFAULT_STATIC_QRIS);
        Setting::put('qris_merchant_name', config('app.name'));

        /*
         * Secrets are deliberately NOT seeded:
         *  - QRIS-ify API key falls back to QrisifyService::DEFAULT_API_KEY and is
         *    meant to be entered from the owner QRIS settings page.
         *  - The webhook secret is generated on first use by WebhookHelper.
         */
        if (Setting::get('qrisify_enabled') === null) {
            Setting::put('qrisify_enabled', '1');
        }
    }
}
