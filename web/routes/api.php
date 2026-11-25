<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\TableController;
use Illuminate\Support\Facades\Route;

// Prefijo: /api/v1 (ver bootstrap/app.php)

Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');

Route::middleware(['auth:sanctum', 'active'])->name('api.')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // App del mesero
    Route::middleware('role:mesero')->group(function () {
        Route::get('/tables', [TableController::class, 'index'])->name('tables.index');
        Route::get('/tables/{table}', [TableController::class, 'show'])->name('tables.show');
        Route::get('/tables/{table}/bill', [TableController::class, 'bill'])->name('tables.bill');
        Route::post('/tables/{table}/payments', [TableController::class, 'charge'])->name('tables.charge');

        Route::get('/categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::get('/products', [CatalogController::class, 'products'])->name('products.index');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    });

    // Cocina (y administración)
    Route::middleware('role:cocina,admin')->group(function () {
        Route::get('/kitchen/orders', [KitchenController::class, 'orders'])->name('kitchen.orders');
        Route::post('/kitchen/orders/{order}/advance', [KitchenController::class, 'advance'])->name('kitchen.advance');
        Route::patch('/products/{product}/availability', [KitchenController::class, 'availability'])->name('products.availability');
    });
});
