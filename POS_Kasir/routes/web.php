<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerMenuController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QrCenterController;
use App\Http\Controllers\QrisController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/', [CustomerMenuController::class, 'index'])->name('menu');
Route::get('/menu', [CustomerMenuController::class, 'index'])->name('menu.alias');
// Shop-wide tools: the QR stand printer is for the admin, not for customers.
Route::get('/qr-center', [QrCenterController::class, 'index'])
    ->middleware(['auth', 'admin'])
    ->name('qr-center');
Route::get('/receipt/{invoice}', [OrderController::class, 'receipt'])
    ->where('invoice', '[A-Za-z0-9\-]+')
    ->name('receipt');
Route::get('/riwayat', [CustomerHistoryController::class, 'index'])->name('history');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

/*
|--------------------------------------------------------------------------
| Owner / kasir area
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [OwnerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::get('/belum-bayar', [ReceivableController::class, 'index'])->name('unpaid');

    // Dynamic QRIS and the gateway/webhook credentials are admin-only.
    Route::get('/qris', [QrisController::class, 'view'])
        ->middleware('admin')
        ->name('qris');
});
