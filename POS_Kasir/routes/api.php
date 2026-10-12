<?php

use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PollingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QrisController;
use App\Http\Controllers\QrisifyController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON API consumed by the front-end (fetch)
|--------------------------------------------------------------------------
|
| The dashboard and menu pages authenticate with the session cookie, so these
| routes run through the "web" middleware group to get session + CSRF support.
|
*/

Route::middleware('web')->group(function () {
    /* Customer facing */
    Route::post('/orders/checkout', [OrderController::class, 'checkout'])->name('orders.checkout');

    // Looked up by phone number with no customer account to authenticate
    // against, so the only defence against walking a range of numbers and
    // reading other people's orders is a request limit.
    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/orders/status/{invoice}', [OrderController::class, 'status'])
            ->where('invoice', '[A-Za-z0-9\-]+')
            ->name('orders.status');
        Route::get('/orders/history', [CustomerHistoryController::class, 'show'])->name('orders.history');
    });

    /* Owner facing */
    Route::middleware('auth')->group(function () {
        // Owner-internal polling only. Each item carries the customer name, phone
        // number, invoice and payment status, so stepping last_id upward from 0
        // dumps the whole customer book. It must never be reachable anonymously.
        Route::get('/orders/check-new', [PollingController::class, 'checkNew'])->name('orders.check-new');

        Route::get('/owner/orders', [OwnerDashboardController::class, 'getActiveOrders'])->name('owner.orders');
        Route::post('/owner/item/status', [OwnerDashboardController::class, 'updateItemStatus'])->name('owner.item-status');
        Route::post('/owner/payment/status', [OwnerDashboardController::class, 'updatePaymentStatus'])->name('owner.payment-status');

        /* Unpaid bills, grouped by customer so the cashier can chase one person once */
        Route::get('/owner/unpaid', [ReceivableController::class, 'list'])->name('owner.unpaid.list');
        Route::post('/owner/unpaid/{saleId}/paid', [ReceivableController::class, 'markPaid'])
            ->where('saleId', '[0-9]+')
            ->name('owner.unpaid.paid');

        Route::get('/owner/products', [ProductController::class, 'list'])->name('owner.products.index');
        Route::post('/owner/products', [ProductController::class, 'save'])->name('owner.products.save');
        Route::post('/owner/products/toggle', [ProductController::class, 'toggleActive'])->name('owner.products.toggle');
        Route::delete('/owner/products/{product}', [ProductController::class, 'destroy'])->name('owner.products.destroy');

        Route::get('/owner/reports/data', [ReportController::class, 'getData'])->name('owner.reports.data');
        Route::get('/owner/reports/export-excel', [ReportController::class, 'exportExcel'])->name('owner.reports.excel');
        Route::get('/owner/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('owner.reports.pdf');

        /* Admin only: dynamic QRIS, gateway credentials, webhook secret */
        Route::middleware('admin')->group(function () {
            Route::get('/owner/qris/settings', [QrisController::class, 'getSettings'])->name('owner.qris.get');
            Route::post('/owner/qris/settings', [QrisController::class, 'saveSettings'])->name('owner.qris.save');
            Route::post('/owner/qris/test', [QrisController::class, 'testGenerate'])->name('owner.qris.test');

            Route::get('/owner/qrisify/settings', [QrisifyController::class, 'getSettings'])->name('owner.qrisify.get');
            Route::post('/owner/qrisify/settings', [QrisifyController::class, 'saveSettings'])->name('owner.qrisify.save');

            Route::get('/owner/webhook/config', [WebhookController::class, 'getConfig'])->name('owner.webhook.config');
            Route::post('/owner/webhook/secret/regenerate', [WebhookController::class, 'regenerateSecret'])->name('owner.webhook.regenerate');
            Route::post('/owner/webhook/secret/update', [WebhookController::class, 'updateSecret'])->name('owner.webhook.update');
        });

        /* Web push notifications */
        Route::get('/owner/push', [PushSubscriptionController::class, 'show'])->name('owner.push.show');
        Route::post('/owner/push/subscribe', [PushSubscriptionController::class, 'store'])->name('owner.push.subscribe');
        Route::post('/owner/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('owner.push.unsubscribe');
        Route::post('/owner/push/test', [PushSubscriptionController::class, 'test'])->name('owner.push.test');
    });

    /*
    |----------------------------------------------------------------------
    | Public payment webhooks
    |----------------------------------------------------------------------
    | Called by external systems, therefore excluded from CSRF verification
    | and authenticated through the shared secret / HMAC signature instead.
    */
    Route::post('/webhook/payment', [WebhookController::class, 'handlePayment'])->name('webhooks.payment');
    Route::post('/webhook/qris', [WebhookController::class, 'handlePayment'])->name('webhooks.qris');
});
