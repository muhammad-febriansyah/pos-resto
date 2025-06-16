<?php

use App\Http\Controllers\CashierController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KasirController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware('auth')->group(function () {

    Route::get('kasir', [KasirController::class, 'index'])->name('kasir');
    Route::get('/cashier', [CashierController::class, 'index'])->name('cashier.index');
    Route::post('/pos/process-sale', [CashierController::class, 'processSale'])->name('pos.process_sale');
});
Route::get('home', [HomeController::class, 'index'])->name('home');
Route::get('login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');
Route::post('/duitku/callback', [CashierController::class, 'callback']);
Route::get('/test', function () {
    $penjualan = \App\Models\Penjualan::where('invoice_number', 'INV202506168LC6SB')->first();

    if (!$penjualan) {
        return 'Penjualan tidak ditemukan';
    }

    $penjualan->status = 'paid';
    $penjualan->save();

    return 'Berhasil update: ' . $penjualan->status;
});
Route::get('/penjualan/{penjualan}/print', [CashierController::class, 'printInvoice'])
    ->name('penjualan.print'); // Beri nama rute ini 'penjualan.print'
Route::get('/sale/success', [CashierController::class, 'saleSuccess'])->name('sale.success');

// require __DIR__ . '/settings.php';
// require __DIR__ . '/auth.php';
