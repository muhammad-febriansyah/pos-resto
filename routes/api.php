<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;



Route::post('/pos/process-sale', [CashierController::class, 'processSale'])->name('pos.process_sale')->middleware('auth:sanctum');
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

Route::get('/setting', [ApiController::class, 'setting'])->name('setting');
Route::get('/faq', [ApiController::class, 'faq'])->name('faq');
Route::get('/KebijakanPrivasi', [ApiController::class, 'KebijakanPrivasi'])->name('KebijakanPrivasi');
Route::get('/syaratKetentuan', [ApiController::class, 'syaratKetentuan'])->name('syaratKetentuan');

Route::post('/register', [AuthController::class, 'register']);
Route::get('auth/google/redirect', [AuthController::class, 'redirect']);
Route::get('auth/google/callback', [AuthController::class, 'callback']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/duitku/callback', [ApiController::class, 'handleDuitkuCallback']);
Route::post('/sales/check-duitku-status', [ApiController::class, 'checkDuitkuStatus'])->middleware('auth:sanctum'); // Tambahkan middleware sesuai kebutuhan
Route::post('/midtrans/callback', [ApiController::class, 'midtransCallback']);
Route::get('/trx', [ApiController::class, 'trx']);
Route::get('/detailtrx', [ApiController::class, 'detailtrx']);
Route::post('/confirm-cash-payment', [ApiController::class, 'confirmCashPayment'])->name('api.confirmCashPayment');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/userprofile', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::get('/kategori', [ApiController::class, 'kategori']);
    Route::get('/promoProduct', [ApiController::class, 'promoProduct']);
    Route::get('/productHome', [ApiController::class, 'productHome']);
    Route::get('/product', [ApiController::class, 'product']);
    Route::get('/productByKategori/{kategori_id}', [ApiController::class, 'productByKategori']);
    Route::get('/meja', [ApiController::class, 'meja']);
    Route::get('/detailcheckout/{invoiceNumber}', [ApiController::class, 'detailcheckout']);

    Route::post('/wishlist/add', [ApiController::class, 'addToWishlist']);
    Route::post('/wishlist/remove', [ApiController::class, 'removeFromWishlist']);
    Route::get('/wishlist', [ApiController::class, 'getWishlist']);

    Route::post('/product-ratings', [ApiController::class, 'storeRating']);
    Route::get('/ratings', [ApiController::class, 'getRating']); // Mengambil semua rating (pertimbangkan paginasi)
    Route::get('/ratings/product/{productId}', [ApiController::class, 'getRatingsByProduct']); // Mengambil rating berdasarkan produk
    Route::get('/ratings/user', [ApiController::class, 'getUserRatings']); // Mengambil rating yang diberikan oleh user yang login
    Route::put('/ratings/{ratingId}', [ApiController::class, 'updateRating']); // Mengupdate rating
    Route::delete('/ratings/{ratingId}', [ApiController::class, 'deleteRating']); // Menghapus rating
    Route::get('/ratings/check', [ApiController::class, 'checkExistingRating']); // Mengecek rating yang sudah ada
    Route::get('/ratings/stats/{productId}', [ApiController::class, 'getRatingStatsProduct']); // Mengambil statistik rating produk
    // Route::get('/ratings/stats/{productId}', [ApiController::class, 'getRatingStats']);


    Route::post('/sales/process', [ApiController::class, 'processSale']);
    Route::get('/duitku/return', [ApiController::class, 'handleDuitkuReturn']);


    Route::get('/user', [ProfileController::class, 'show']);
    Route::post('/user/update', [ProfileController::class, 'update']);
    Route::post('/user/change-password', [ProfileController::class, 'updatePassword']);


    Route::post('/logout', [AuthController::class, 'logout']);
});
