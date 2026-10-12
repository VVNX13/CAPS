<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use App\Support\QrisHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrisController extends Controller
{
    public function view(): View
    {
        return view('owner.qris');
    }

    /**
     * Current static QRIS payload plus the parsed merchant details.
     */
    public function getSettings(): JsonResponse
    {
        $payload = QrisHelper::getStaticPayload();

        return ApiResponse::success([
            'static_payload' => $payload,
            'details' => QrisHelper::parseQrisDetails($payload),
            'qr_image_url' => QrisHelper::getQrImageUrl($payload, 250),
        ]);
    }

    /**
     * Store the merchant's static QRIS string.
     */
    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'static_payload' => ['required', 'string'],
        ], [
            'static_payload.required' => 'String QRIS Statis tidak boleh kosong',
        ]);

        $payload = trim($data['static_payload']);

        if (! str_starts_with($payload, '000201')) {
            return ApiResponse::error('Format QRIS tidak valid. String QRIS standar harus diawali dengan 000201');
        }

        QrisHelper::setStaticPayload($payload);

        return ApiResponse::success([
            'static_payload' => $payload,
            'details' => QrisHelper::parseQrisDetails($payload),
            'qr_image_url' => QrisHelper::getQrImageUrl($payload, 250),
        ], 'QRIS Statis berhasil disimpan & diaktifkan!');
    }

    /**
     * Generate a dynamic QRIS string for testing purposes.
     */
    public function testGenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'static_payload' => ['nullable', 'string'],
        ]);

        $amount = (float) ($data['amount'] ?? 10000);
        $customPayload = trim($data['static_payload'] ?? '') ?: QrisHelper::getStaticPayload();

        $dynamic = QrisHelper::convertStaticToDynamic($customPayload, $amount);

        return ApiResponse::success([
            'amount' => $amount,
            'dynamic_payload' => $dynamic,
            'crc16' => substr($dynamic, -4),
            'qr_image_url' => QrisHelper::getQrImageUrl($dynamic, 300),
            'details' => QrisHelper::parseQrisDetails($dynamic),
        ], 'Dynamic QRIS berhasil di-generate!');
    }
}
