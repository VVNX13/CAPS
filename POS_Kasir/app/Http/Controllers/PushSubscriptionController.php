<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    /**
     * Public VAPID key and the caller's current subscription state.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        return ApiResponse::success([
            'enabled' => PushNotificationService::isConfigured(),
            'public_key' => config('push.public_key'),
            'subscribed' => PushNotificationService::forUser($user)->isNotEmpty(),
            'devices' => PushNotificationService::forUser($user)->map(fn (PushSubscription $s) => [
                'id' => $s->id,
                'user_agent' => $s->user_agent,
                'last_used_at' => $s->last_used_at?->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Store (or refresh) a browser push subscription.
     */
    public function store(Request $request): JsonResponse
    {
        if (! PushNotificationService::isConfigured()) {
            return ApiResponse::error('Push notification belum dikonfigurasi. Jalankan: php artisan push:vapid-keys', 503);
        }

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ], [
            'endpoint.required' => 'Endpoint subscription tidak valid',
            'keys.p256dh.required' => 'Kunci p256dh tidak valid',
            'keys.auth.required' => 'Kunci auth tidak valid',
        ]);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ]
        );

        return ApiResponse::success(
            ['id' => $subscription->id],
            'Notifikasi push diaktifkan untuk perangkat ini'
        );
    }

    /**
     * Remove a subscription by endpoint.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        $deleted = PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return ApiResponse::success(
            ['deleted' => $deleted],
            $deleted > 0 ? 'Notifikasi push dimatikan' : 'Subscription tidak ditemukan'
        );
    }

    /**
     * Fire a test notification at the caller's own devices.
     */
    public function test(Request $request): JsonResponse
    {
        if (! PushNotificationService::isConfigured()) {
            return ApiResponse::error('Push notification belum dikonfigurasi. Jalankan: php artisan push:vapid-keys', 503);
        }

        $user = $request->user();

        if (PushNotificationService::forUser($user)->isEmpty()) {
            return ApiResponse::error('Belum ada perangkat yang terdaftar untuk akun ini', 422);
        }

        SendPushNotification::dispatch(
            [(int) $user->id],
            [
                'title' => '✅ Notifikasi aktif!',
                'body' => 'Taman Teras Cafe · Anda akan menerima pemberitahuan setiap ada pesanan baru.',
                'tag' => 'push-test',
                'url' => route('owner.dashboard'),
            ]
        );

        return ApiResponse::success(null, 'Notifikasi tes dikirim. Pastikan HP dalam keadaan terhubung internet.');
    }
}
