<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\CustomerMenuController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QrCenterController;
use App\Http\Controllers\QrisController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
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
    Route::get('/stok', [ProductController::class, 'stockView'])->name('stock');
    // User management (admin only)
    Route::get('/users', [UserController::class, 'index'])->middleware('admin')->name('users');
    Route::get('/users/list', [UserController::class, 'list'])->middleware('admin')->name('users.list');
    Route::post('/users', [UserController::class, 'store'])->middleware('admin')->name('users.store');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('admin')->name('users.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('admin')->name('users.destroy');
    Route::get('/belum-bayar', [ReceivableController::class, 'index'])->name('unpaid');

    // Dynamic QRIS and the gateway/webhook credentials are admin-only.
    Route::get('/qris', [QrisController::class, 'view'])
        ->middleware('admin')
        ->name('qris');
    Route::get('/promo', [PromoCodeController::class, 'index'])
        ->middleware('admin')
        ->name('promos');
});

/*
|--------------------------------------------------------------------------
| Dapur (Kitchen Display System)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'kitchen'])->prefix('dapur')->name('dapur.')->group(function () {
    Route::get('/dashboard', [KitchenController::class, 'index'])->name('dashboard');
});
