<?php

namespace App\Http\Controllers;

use App\Services\QrisifyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrisifyController extends Controller
{
    /**
     * QRIS-ify gateway credentials currently stored in the settings table.
     */
    public function getSettings(): JsonResponse
    {
        $apiKey = QrisifyService::getApiKey();

        return ApiResponse::success([
            'api_key' => $apiKey,
            'api_key_masked' => substr($apiKey, 0, 10).'...'.substr($apiKey, -4),
            'enabled' => QrisifyService::isEnabled(),
        ]);
    }

    /**
     * Persist the QRIS-ify API key and/or enabled flag.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['api_key'])) {
            QrisifyService::setApiKey($data['api_key']);
        }

        if (isset($data['enabled'])) {
            QrisifyService::setEnabled((bool) $data['enabled']);
        }

        return ApiResponse::success(null, 'Pengaturan QRIS-ify berhasil disimpan!');
    }
}
