<?php

use App\Http\Controllers\CashierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/pos/process-sale', [CashierController::class, 'processSale'])->name('pos.process_sale')->middleware('auth:sanctum');

// Route untuk Duitku Callback (tidak perlu auth, karena dari Duitku langsung)
Route::post('/callback', [CashierController::class, 'duitkuCallback'])->name('callback');
Route::get('/test', function () {
    $penjualan = \App\Models\Penjualan::where('invoice_number', 'INV202506168LC6SB')->first();

    if (!$penjualan) {
        return 'Penjualan tidak ditemukan';
    }

    $penjualan->status = 'paid';
    $penjualan->save();

    return 'Berhasil update: ' . $penjualan->status;
});
