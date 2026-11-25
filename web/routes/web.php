<?php

use App\Http\Controllers\Admin\ReportCsvController;
use App\Http\Controllers\Auth\WebAuthController;
use App\Livewire\Admin;
use App\Livewire\Kitchen\Availability;
use App\Livewire\Kitchen\Board;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'create'])->name('login');
    Route::post('/login', [WebAuthController::class, 'store'])->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [WebAuthController::class, 'destroy'])->name('logout');

    // Panel de cocina (también lo puede ver el administrador).
    Route::middleware('role:cocina,admin')->prefix('cocina')->name('kitchen.')->group(function () {
        Route::get('/', Board::class)->name('board');
        Route::get('/agotados', Availability::class)->name('availability');
    });

    // Panel de administración.
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\Dashboard::class)->name('dashboard');
        Route::get('/ordenes', Admin\Orders\Index::class)->name('orders.index');
        Route::get('/ordenes/{order}', Admin\Orders\Show::class)->name('orders.show');
        Route::get('/pagos', Admin\Payments::class)->name('payments');
        Route::get('/platillos', Admin\Products\Index::class)->name('products.index');
        Route::get('/platillos/nuevo', Admin\Products\Form::class)->name('products.create');
        Route::get('/platillos/{product}/editar', Admin\Products\Form::class)->name('products.edit');
        Route::get('/categorias', Admin\Categories::class)->name('categories');
        Route::get('/mesas', Admin\Tables::class)->name('tables');
        Route::get('/usuarios', Admin\Users::class)->name('users');
        Route::get('/corte-caja', Admin\CashClosings::class)->name('cash');
        Route::get('/reportes', Admin\Reports::class)->name('reports');
        Route::get('/reportes/{tipo}.csv', ReportCsvController::class)->name('reports.csv');
        Route::get('/bitacora', Admin\AuditLogs::class)->name('audit');
    });
});
