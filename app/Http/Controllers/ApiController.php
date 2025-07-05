<?php

namespace App\Http\Controllers;

use App\Models\DetailPenjualan;
use App\Models\Faq;
use App\Models\Kategori;
use App\Models\KebijakanPrivasi;
use App\Models\Meja;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\Rating;
use App\Models\Setting;
use App\Models\SyaratKetentuan;
use App\Models\User;
use App\Models\Wishlist;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class ApiController extends Controller
{
    /**
     * Mengambil pengaturan aplikasi.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setting()
    {
        $setting = Setting::first();
        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }

    public function syaratKetentuan()
    {
        $data = SyaratKetentuan::first();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function KebijakanPrivasi()
    {
        $data = KebijakanPrivasi::first();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function faq()
    {
        $data = Faq::all();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Mengambil semua kategori produk.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function kategori()
    {
        $data = Kategori::latest()->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Mengambil produk-produk yang sedang promo dan tersedia.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function promoProduct()
    {
        $data = Produk::where('promo', 1)->where('stok', '>', 0)->where('is_active', 1)->latest()->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Mengambil beberapa produk untuk tampilan beranda.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function productHome()
    {
        $data = Produk::where('is_active', 1)->where('stok', '>', 0)->where('promo', 0)->limit(5)->latest()->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Mengambil semua produk yang aktif dan tersedia.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function product()
    {
        $data = Produk::where('is_active', 1)->where('stok', '>', 0)->latest()->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function productByKategori($kategori_id)
    {
        $query = Produk::query();

        $query->where('kategori_id', $kategori_id);
        $query->where('is_active', 1);
        $query->where('stok', '>', 0);

        $data = $query->get();

        if ($data->isEmpty()) {
            Log::warning("Tidak ada produk ditemukan untuk kategori_id $kategori_id");
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getWishlist(Request $request)
    {
        // Gunakan Auth::id() untuk mendapatkan ID user yang sedang login
        $userId = Auth::id(); // Menggunakan ID user yang terautentikasi
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $wishlist = Wishlist::where('user_id', $userId)->latest()->get();
        return response()->json([
            'data' => $wishlist,
            'success' => true,
        ]);
    }

    // Fungsi baru: Menambah produk ke wishlist
    public function addToWishlist(Request $request)
    {
        $request->validate([
            'produk_id' => 'required|exists:produks,id',
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        // Gunakan firstOrCreate agar tidak double insert
        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $userId,
            'produk_id' => $request->produk_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke wishlist.',
            'data' => $wishlist,
        ], 201);
    }


    // Fungsi baru: Menghapus produk dari wishlist
    public function removeFromWishlist(Request $request)
    {
        $request->validate([
            'produk_id' => 'required|exists:produks,id',
        ]);

        $userId = Auth::id();
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $deleted = Wishlist::where('user_id', $userId)
            ->where('produk_id', $request->produk_id)
            ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus dari wishlist.',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan di wishlist atau gagal dihapus.',
            ], 404); // 404 Not Found
        }
    }

    public function storeRating(Request $request)
    {
        // Validasi input dari request
        $validated = $request->validate([
            'product_id' => 'required|exists:produks,id', // Mengacu ke tabel 'produks'
            'transaction_id' => 'required|exists:penjualans,id', // Mengacu ke tabel 'penjualans'
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        // Pastikan user terautentikasi sebelum melanjutkan
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. User not logged in.',
            ], 401);
        }

        // Mencari atau membuat rating baru.
        // Jika rating dengan user_id, produk_id, dan transaction_id yang sama sudah ada,
        // maka akan diupdate. Jika tidak, akan dibuat baru.
        $rating = Rating::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'produk_id' => $validated['product_id'], // Menggunakan 'produk_id' sesuai tabel ratings
                'transaction_id' => $validated['transaction_id'],
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
            ]
        );

        // Mengembalikan respons JSON sukses
        return response()->json([
            'success' => true,
            'message' => 'Rating produk berhasil disimpan.',
            'data' => $rating,
        ], 200); // Menggunakan kode status 200 (OK)
    }

    /**
     * Get all ratings (consider adding pagination or filters for production).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRating()
    {
        // Mengambil semua data rating
        $data = Rating::all();

        // Mengembalikan respons JSON dengan semua data rating
        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Get ratings for a specific product.
     *
     * @param  int  $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRatingsByProduct($productId)
    {
        // Mengambil rating berdasarkan produk_id
        $ratings = Rating::where('produk_id', $productId)->get(); // Menggunakan 'produk_id'

        // Mengembalikan respons JSON dengan rating produk
        return response()->json([
            'success' => true,
            'data' => $ratings,
        ], 200);
    }

    /**
     * Get ratings given by the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserRatings(Request $request)
    {
        // Pastikan user terautentikasi
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. User not logged in.',
            ], 401);
        }

        // Mengambil rating berdasarkan user_id dari user yang sedang login
        $ratings = Rating::where('user_id', $request->user()->id)->get();

        // Mengembalikan respons JSON dengan rating user
        return response()->json([
            'success' => true,
            'data' => $ratings,
        ], 200);
    }

    /**
     * Update an existing rating.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $ratingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRating(Request $request, $ratingId)
    {
        // Mencari rating berdasarkan ID dan user_id untuk memastikan hanya pemilik yang bisa mengupdate
        $rating = Rating::where('id', $ratingId)
            ->where('user_id', $request->user()->id)
            ->first();

        // Jika rating tidak ditemukan atau user tidak berhak, kembalikan error
        if (!$rating) {
            return response()->json([
                'success' => false,
                'message' => 'Rating not found or you are not authorized to update it.',
            ], 404); // Menggunakan kode status 404 (Not Found)
        }

        // Validasi input untuk update rating
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        // Melakukan update pada rating
        $rating->update([
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
        ]);

        // Mengembalikan respons JSON sukses
        return response()->json([
            'success' => true,
            'message' => 'Rating berhasil diperbarui.',
            'data' => $rating,
        ], 200);
    }

    /**
     * Delete a rating.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $ratingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteRating(Request $request, $ratingId)
    {
        // Mencari rating berdasarkan ID dan user_id untuk memastikan hanya pemilik yang bisa menghapus
        $rating = Rating::where('id', $ratingId)
            ->where('user_id', $request->user()->id)
            ->first();

        // Jika rating tidak ditemukan atau user tidak berhak, kembalikan error
        if (!$rating) {
            return response()->json([
                'success' => false,
                'message' => 'Rating not found or you are not authorized to delete it.',
            ], 404);
        }

        // Menghapus rating
        $rating->delete();

        // Mengembalikan respons JSON sukses
        return response()->json([
            'success' => true,
            'message' => 'Rating berhasil dihapus.',
        ], 200);
    }

    /**
     * Check if a user has an existing rating for a specific product and transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkExistingRating(Request $request)
    {
        // Validasi input dari request
        $validated = $request->validate([
            'product_id' => 'required|exists:produks,id', // Mengacu ke tabel 'produks'
            'transaction_id' => 'required|exists:penjualans,id', // Mengacu ke tabel 'penjualans'
        ]);

        // Pastikan user terautentikasi
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. User not logged in.',
            ], 401);
        }

        // Mencari rating berdasarkan user_id, produk_id, dan transaction_id
        $rating = Rating::where('user_id', $request->user()->id)
            ->where('produk_id', $validated['product_id']) // Menggunakan 'produk_id' sesuai tabel ratings
            ->where('transaction_id', $validated['transaction_id'])
            ->first();

        // Mengembalikan respons JSON. 'data' akan berisi rating jika ditemukan, atau null.
        return response()->json([
            'success' => true,
            'data' => $rating,
        ], 200);
    }

    /**
     * Get rating statistics for a specific product.
     *
     * @param  int  $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRatingStats($productId)
    {
        // Menghitung total rating untuk produk tertentu
        $totalRatings = Rating::where('produk_id', $productId)->count(); // Menggunakan 'produk_id'
        // Menghitung rata-rata rating
        $averageRating = Rating::where('produk_id', $productId)->avg('rating'); // Menggunakan 'produk_id'
        // Menghitung jumlah rating per bintang
        $ratingsCountByStar = Rating::where('produk_id', $productId) // Menggunakan 'produk_id'
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        // Mengembalikan respons JSON dengan statistik rating
        return response()->json([
            'success' => true,
            'data' => [
                'total_ratings' => $totalRatings,
                'average_rating' => round($averageRating, 2), // Bulatkan rata-rata rating
                'ratings_count_by_star' => $ratingsCountByStar,
            ],
        ], 200);
    }


    public function getRatingStatsProduct($productId)
    {
        $totalRatings = Rating::where('produk_id', $productId)->count(); // Use 'produk_id'
        $averageRating = Rating::where('produk_id', $productId)->avg('rating'); // Use 'produk_id'

        $ratingsCountByStar = Rating::where('produk_id', $productId) // Use 'produk_id'
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'desc')
            ->get();

        // Format for Flutter's RatingStats model
        $ratingDistribution = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ];

        foreach ($ratingsCountByStar as $starCount) {
            $ratingDistribution[$starCount->rating] = $starCount->count;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_ratings' => $totalRatings,
                'average_rating' => round($averageRating ?? 0, 2), // Round and handle null if no ratings
                'rating_distribution' => $ratingDistribution,
            ],
        ], 200);
    }
    /**
     * Mengambil semua data penjualan (transaksi).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function trx(Request $request)
    {
        $userId = $request->userId;
        $data = Penjualan::with('produk')
            ->where('customer_id', $userId)
            ->latest()
            ->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    public function detailtrx()
    {
        $data = DetailPenjualan::latest()->get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function detailcheckout($invoiceNumber)
    {
        $data = DetailPenjualan::whereHas('penjualan', function ($q) use ($invoiceNumber) {
            $q->where('invoice_number', $invoiceNumber);
        })->with('produk')->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    /**
     * Mengambil semua meja yang statusnya 'tersedia'.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function meja()
    {
        $data = Meja::get();
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Memproses penjualan, baik tunai maupun melalui Duitku.
     * Termasuk validasi stok, perhitungan total, dan inisiasi pembayaran.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function processSale(Request $request)
    {
        $request->validate([
            'cartItems' => 'required|array|min:1',
            'cartItems.*.id' => 'required|exists:produks,id',
            'cartItems.*.qty' => 'required|integer|min:1',
            'paymentMethod' => 'required|string|in:cash,midtrans',
            'customerId' => 'nullable|exists:users,id',
            'mejaId' => 'nullable|exists:mejas,id',
            'type' => 'required|string|in:dine_in,take_away,delivery',
            // 'amountPaid' is no longer required for cash as no change is calculated
        ]);

        DB::beginTransaction();
        $selectedMeja = null;

        try {
            $settings = Setting::firstOrFail();
            $ppnPercentage = $settings->ppn;
            $biayaLayananDefault = $settings->biaya_lainnya;

            $subTotal = 0;
            $laba = 0;

            foreach ($request->cartItems as $item) {
                $produk = Produk::findOrFail($item['id']);
                if ($produk->stok < $item['qty']) {
                    throw new Exception("Stok produk '{$produk->nama_produk}' tidak cukup.");
                }
                $subTotal += $produk->harga_jual * $item['qty'];
                $laba += ($produk->harga_jual - $produk->harga_beli) * $item['qty'];
            }

            $ppnAmount = ($subTotal * $ppnPercentage) / 100;
            $biayaLayanan = $request->type === 'dine_in' ? $biayaLayananDefault : 0;
            $total = $subTotal + $ppnAmount + $biayaLayanan;

            $invoiceNumber = 'INV' . date('Ymd') . strtoupper(Str::random(6));

            if ($request->type === 'dine_in' && $request->mejaId) {
                $selectedMeja = Meja::find($request->mejaId);
                if (!$selectedMeja) {
                    throw new Exception("Meja tidak ditemukan.");
                }
                if ($selectedMeja->status !== 'tersedia') {
                    throw new Exception("Meja {$selectedMeja->nama} tidak tersedia.");
                }
                $selectedMeja->status = 'dipakai';
                $selectedMeja->save();
            }

            $penjualanData = [
                'invoice_number' => $invoiceNumber,
                'user_id' => Auth::user()->id ?? 1,
                'customer_id' => $request->customerId,
                'meja_id' => $request->mejaId,
                'payment_method' => $request->paymentMethod,
                'type' => $request->type,
                'sub_total' => $subTotal,
                'ppn' => $ppnPercentage,
                'biaya_layanan' => $biayaLayanan ?? 0,
                'total' => $total,
                'laba' => $laba,
                'status' => 'pending', // Default status for all transactions initially
            ];

            if ($request->paymentMethod === 'cash') {
                // For cash payments, the status is 'pending' until confirmed by cashier
                $penjualanData['status'] = 'pending';
                $penjualan = Penjualan::create($penjualanData);

                foreach ($request->cartItems as $item) {
                    $produk = Produk::find($item['id']);
                    // Decrement stock immediately even for pending cash payments
                    // This assumes stock is reserved upon order creation.
                    // If stock should only be decremented upon 'paid' status, move this to a confirmation step.
                    $produk->decrement('stok', $item['qty']);
                    DetailPenjualan::create([
                        'penjualan_id' => $penjualan->id,
                        'produk_id' => $produk->id,
                        'qty' => $item['qty'],
                        'harga_saat_jual' => $produk->harga_jual,
                        'subtotal_item' => $produk->harga_jual * $item['qty'],
                    ]);
                }

                // If it's a dine-in and a table was selected, set it to 'dipakai' (used)
                // It will be set to 'tersedia' (available) upon payment confirmation.
                // This logic is already handled above before the `if ($request->paymentMethod === 'cash')` block.

                DB::commit();

                $setting = Setting::first();
                $appName = $setting->site_name;
                $customerPhoneNumber = $penjualan->customer->phone ?? env('DEFAULT_CUSTOMER_PHONE', null);

                if ($customerPhoneNumber) {
                    // Send pending message for cash payment
                    $message = $this->buildCashPendingMessage($penjualan, $appName);
                    $this->sendWhatsAppMessage($customerPhoneNumber, $message);
                } else {
                    Log::warning("WhatsApp not sent: Customer phone number not found for invoice {$invoiceNumber}.");
                }


                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan tunai berhasil dibuat, menunggu pembayaran di kasir.',
                    'invoice' => $penjualan,
                    'status_penjualan' => 'pending', // Explicitly state pending
                ]);
            }

            if ($request->paymentMethod === 'midtrans') {
                $penjualan = Penjualan::create($penjualanData);

                foreach ($request->cartItems as $item) {
                    $produk = Produk::find($item['id']);
                    DetailPenjualan::create([
                        'penjualan_id' => $penjualan->id,
                        'produk_id' => $produk->id,
                        'qty' => $item['qty'],
                        'harga_saat_jual' => $produk->harga_jual,
                        'subtotal_item' => $produk->harga_jual * $item['qty'],
                    ]);
                }

                Config::$serverKey = env('MIDTRANS_SERVERKEY');
                Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
                Config::$isSanitized = env('MIDTRANS_IS_SANITIZED');
                Config::$is3ds = env('MIDTRANS_IS_3DS');

                $customer = $request->customerId ? \App\Models\User::find($request->customerId) : Auth::user();
                $customerName = $customer?->name ?? 'Pelanggan';
                $customerEmail = $customer?->email ?? 'guest@example.com';
                $customerPhone = preg_replace('/[^0-9]/', '', $customer?->phone ?? '081234567890');

                $midtransItemDetails = [];
                foreach ($request->cartItems as $item) {
                    $produk = Produk::findOrFail($item['id']);
                    $midtransItemDetails[] = [
                        'id' => $produk->id,
                        'price' => (int) round($produk->harga_jual),
                        'quantity' => (int) $item['qty'],
                        'name' => $produk->nama_produk,
                    ];
                }

                if ($ppnAmount > 0) {
                    $midtransItemDetails[] = [
                        'id' => 'PPN',
                        'price' => (int) round($ppnAmount),
                        'quantity' => 1,
                        'name' => 'Pajak PPN (' . $ppnPercentage . '%)',
                    ];
                }

                if ($biayaLayanan > 0) {
                    $midtransItemDetails[] = [
                        'id' => 'BIAYA_LAYANAN',
                        'price' => (int) round($biayaLayanan),
                        'quantity' => 1,
                        'name' => 'Biaya Layanan',
                    ];
                }

                $grossAmount = 0;
                foreach ($midtransItemDetails as $detail) {
                    $grossAmount += $detail['price'] * $detail['quantity'];
                }

                $penjualan->update(['total' => $grossAmount]);

                $transaction_details = [
                    'order_id' => $invoiceNumber,
                    'gross_amount' => $grossAmount,
                ];

                $customer_details = [
                    'first_name' => explode(' ', $customerName)[0],
                    'last_name' => count(explode(' ', $customerName)) > 1 ? implode(' ', array_slice(explode(' ', $customerName), 1)) : '',
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'billing_address' => [
                        'first_name' => explode(' ', $customerName)[0],
                        'last_name' => count(explode(' ', $customerName)) > 1 ? implode(' ', array_slice(explode(' ', $customerName), 1)) : '',
                        'address' => $customer?->address ?? '',
                        'city' => $customer?->city ?? '',
                        'postal_code' => $customer?->postal_code ?? '',
                        'phone' => $customerPhone,
                        'country_code' => 'IDN',
                    ],
                    'shipping_address' => [
                        'first_name' => explode(' ', $customerName)[0],
                        'last_name' => count(explode(' ', $customerName)) > 1 ? implode(' ', array_slice(explode(' ', $customerName), 1)) : '',
                        'address' => $customer?->address ?? '',
                        'city' => $customer?->city ?? '',
                        'postal_code' => $customer?->postal_code ?? '',
                        'phone' => $customerPhone,
                        'country_code' => 'IDN',
                    ],
                ];

                $appSchemeFlutter = env('APP_SCHEME_FLUTTER', 'posapp');
                $finish_redirect_url = $appSchemeFlutter . "://payment_status?status=success&order_id={$invoiceNumber}";
                $error_redirect_url = $appSchemeFlutter . "://payment_status?status=error&order_id={$invoiceNumber}";
                $pending_redirect_url = $appSchemeFlutter . "://payment_status?status=pending&order_id={$invoiceNumber}";

                $callbacks = [
                    'finish' => $finish_redirect_url,
                    'error' => $error_redirect_url,
                    'pending' => $pending_redirect_url,
                ];

                $params = [
                    'transaction_details' => $transaction_details,
                    'item_details' => $midtransItemDetails,
                    'customer_details' => $customer_details,
                    'callbacks' => $callbacks,
                    'expiry' => [
                        'unit' => 'minute',
                        'duration' => 120,
                    ],
                ];

                Log::info('Midtrans Snap Parameters', [
                    'params' => $params,
                    'penjualan_id' => $penjualan->id
                ]);

                $snapToken = Snap::getSnapToken($params);

                $penjualan->update([
                    'midtrans_snap_token' => $snapToken,
                    'payment_url' => 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken,
                ]);

                DB::commit();

                $setting = Setting::first();
                $appName = $setting->site_name;
                $customerPhoneNumber = $penjualan->customer->phone ?? env('DEFAULT_CUSTOMER_PHONE', null);

                if ($customerPhoneNumber) {
                    $message = $this->buildPendingMessage($penjualan, $appName, $penjualan->payment_url);
                    $this->sendWhatsAppMessage($customerPhoneNumber, $message);
                } else {
                    Log::warning("WhatsApp not sent: Customer phone number not found for invoice {$invoiceNumber}.");
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi Midtrans berhasil dibuat.',
                    'snapToken' => $snapToken,
                    'invoiceNumber' => $invoiceNumber,
                    'paymentUrl' => 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken,
                    'status_penjualan' => 'pending',
                ]);
            }
        } catch (Exception $e) {
            DB::rollBack();
            if ($selectedMeja && $selectedMeja->status === 'dipakai') {
                $selectedMeja->status = 'tersedia';
                $selectedMeja->save();
            }
            Log::error('Gagal proses penjualan: ' . $e->getMessage(), [
                'request_payload' => $request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle Midtrans notification callback.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function midtransCallback(Request $request)
    {
        Config::$serverKey = env('MIDTRANS_SERVERKEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION');
        Config::$isSanitized = env('MIDTRANS_IS_SANITIZED');
        Config::$is3ds = env('MIDTRANS_IS_3DS');

        $notif = new Notification();

        DB::beginTransaction();
        try {
            $transaction = $notif->transaction_status;
            $type = $notif->payment_type;
            $orderId = $notif->order_id;
            $fraud = $notif->fraud_status;
            $midtransTransactionId = $notif->penjualan_id;

            $penjualan = Penjualan::where('invoice_number', $orderId)->first();

            if (!$penjualan) {
                Log::warning("Midtrans Callback: Invoice {$orderId} not found.", ['notification' => $notif->getResponse()]);
                return response()->json(['message' => 'Invoice not found'], 404);
            }

            Log::info("Midtrans Callback: Processing order {$orderId} with status {$transaction}", ['notification' => $notif->getResponse()]);
            $penjualan->midtrans_transaction_id = $midtransTransactionId;

            if ($transaction == 'capture') {
                if ($fraud == 'challenge') {
                    $penjualan->status = 'challenge';
                } else if ($fraud == 'accept') {
                    $penjualan->status = 'paid';
                    if ($penjualan->detailPenjualan instanceof \Illuminate\Support\Collection || is_array($penjualan->detailPenjualan)) {
                        foreach ($penjualan->detailPenjualan as $detail) {
                            $produk = Produk::find($detail->produk_id);
                            if ($produk) {
                                $produk->decrement('stok', $detail->qty);
                            }
                        }
                    }
                    if ($penjualan->meja_id) {
                        $meja = Meja::find($penjualan->meja_id);
                        if ($meja) {
                            $meja->status = 'tersedia';
                            $meja->save();
                        }
                    }
                    $setting = Setting::first();
                    $appName = $setting->site_name;
                    $customerPhoneNumber = $penjualan->customer->phone;
                    if ($customerPhoneNumber) {
                        $message = $this->buildInvoiceMessage($penjualan, $appName);
                        $this->sendWhatsAppMessage($customerPhoneNumber, $message);
                    } else {
                        Log::warning("WhatsApp not sent: Customer phone number not found for invoice {$orderId}.");
                    }
                }
            } else if ($transaction == 'settlement') {
                $penjualan->status = 'paid';
                if ($penjualan->detailPenjualan instanceof \Illuminate\Support\Collection || is_array($penjualan->detailPenjualan)) {
                    foreach ($penjualan->detailPenjualan as $detail) {
                        $produk = Produk::find($detail->produk_id);
                        if ($produk) {
                            $produk->decrement('stok', $detail->qty);
                        }
                    }
                }
                if ($penjualan->meja_id) {
                    $meja = Meja::find($penjualan->meja_id);
                    if ($meja) {
                        $meja->status = 'tersedia';
                        $meja->save();
                    } else {
                        Log::warning("Midtrans Callback: Meja with ID {$penjualan->meja_id} not found for invoice {$orderId} during settlement.");
                    }
                }
                $setting = Setting::first();
                $appName = $setting->site_name;
                $customerPhoneNumber = $penjualan->customer->phone ?? env('DEFAULT_CUSTOMER_PHONE', null);
                if ($customerPhoneNumber) {
                    $message = $this->buildInvoiceMessage($penjualan, $appName);
                    $this->sendWhatsAppMessage($customerPhoneNumber, $message);
                } else {
                    Log::warning("WhatsApp not sent: Customer phone number not found for invoice {$orderId}.");
                }
            } else if ($transaction == 'pending') {
                $penjualan->status = 'pending';
            } else if ($transaction == 'deny') {
                $penjualan->status = 'cancelled';
                if ($penjualan->meja_id) {
                    $meja = Meja::find($penjualan->meja_id);
                    if ($meja && $meja->status === 'dipakai') {
                        $meja->status = 'tersedia';
                        $meja->save();
                    }
                }
            } else if ($transaction == 'expire') {
                $penjualan->status = 'expired';
                if ($penjualan->meja_id) {
                    $meja = Meja::find($penjualan->meja_id);
                    if ($meja && $meja->status === 'dipakai') {
                        $meja->status = 'tersedia';
                        $meja->save();
                    }
                }
            } else if ($transaction == 'cancel') {
                $penjualan->status = 'cancelled';
                if ($penjualan->meja_id) {
                    $meja = Meja::find($penjualan->meja_id);
                    if ($meja && $meja->status === 'dipakai') {
                        $meja->status = 'tersedia';
                        $meja->save();
                    }
                }
            }

            $penjualan->save();
            DB::commit();

            return response()->json(['message' => 'Notification processed successfully']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing Midtrans callback: ' . $e->getMessage(), [
                'request_payload' => $request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'Error processing notification', 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * Builds a WhatsApp message for a successful invoice.
     *
     * @param Penjualan $penjualan The Penjualan model instance.
     * @param string $appName The name of the application.
     * @param float|null $amountPaid (Optional) Amount paid for cash transactions.
     * @param float|null $change (Optional) Change for cash transactions.
     * @return string
     */
    protected function buildInvoiceMessage(Penjualan $penjualan, string $appName, ?float $amountPaid = null, ?float $change = null): string
    {
        $message = "🌟 *INVOICE PEMBELIAN - " . strtoupper($appName) . " 🌟*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan') . "*,\n";
        $message .= "Terima kasih telah berbelanja di kami! Berikut rincian pesanan Anda:\n\n";

        $message .= "```\n";
        $message .= "--------------------------------------\n";
        $message .= "🧾 INVOICE #" . $penjualan->invoice_number . "\n";
        $message .= "Tanggal: " . Carbon::parse($penjualan->created_at)->translatedFormat('d M Y, H:i') . " WIB\n";
        $message .= "Tipe: " . ucwords(str_replace('_', ' ', $penjualan->type)) . "\n";
        if ($penjualan->type === 'dine_in' && $penjualan->meja) {
            $message .= "Meja: " . $penjualan->meja->nama . "\n";
        }
        $message .= "--------------------------------------\n";
        $message .= "🛒 Detail Pesanan:\n";
        foreach ($penjualan->details as $item) {
            $message .= sprintf(
                "%-20s %3dx Rp%-8s = Rp%s\n",
                Str::limit($item->produk->nama_produk, 20, ''),
                $item->qty,
                number_format($item->produk->harga_jual, 0, ',', '.'),
                number_format($item->subtotal_item, 0, ',', '.')
            );
        }
        $message .= "--------------------------------------\n";
        $message .= "💰 Ringkasan Pembayaran:\n";
        $message .= sprintf("%-25s Rp%s\n", "Sub Total:", number_format($penjualan->sub_total, 0, ',', '.'));
        if ($penjualan->ppn > 0) {
            $message .= sprintf("%-25s Rp%s\n", "PPN (" . $penjualan->ppn . "%):", number_format(($penjualan->sub_total * $penjualan->ppn / 100), 0, ',', '.'));
        }
        if ($penjualan->biaya_layanan > 0) {
            $message .= sprintf("%-25s Rp%s\n", "Biaya Layanan:", number_format($penjualan->biaya_layanan, 0, ',', '.'));
        }
        $message .= "--------------------------------------\n";
        $message .= sprintf("%-25s *Rp%s*\n", "Total Pembayaran:", number_format($penjualan->total, 0, ',', '.'));
        $message .= sprintf("%-25s %s\n", "Metode Pembayaran:", ucwords($penjualan->payment_method));

        // Removed the conditional block for amountPaid and change as it's no longer needed for cash
        // if ($amountPaid !== null && $penjualan->payment_method === 'cash') {
        //     $message .= sprintf("%-25s Rp%s\n", "Uang Dibayar:", number_format($amountPaid, 0, ',', '.'));
        //     $message .= sprintf("%-25s Rp%s\n", "Kembalian:", number_format($change, 0, ',', '.'));
        // }
        $message .= "--------------------------------------\n";
        $message .= "```\n\n";

        $message .= "Pembayaran Anda telah *berhasil* diterima. Kami akan segera memproses pesanan Anda.\n";
        $message .= "Terima kasih telah berbelanja di *" . $appName . "*! Kami menantikan kunjungan Anda kembali 😊\n\n";
        $message .= "© " . Carbon::now()->year . " *" . $appName . "*";

        return $message;
    }

    /**
     * Builds a WhatsApp message for a pending payment (e.g., for Midtrans/online payments).
     * This is a generalized version of buildDuitkuPendingMessage.
     *
     * @param Penjualan $penjualan The Penjualan model instance.
     * @param string $appName The name of the application.
     * @param string|null $paymentUrl (Optional) URL to complete the payment.
     * @return string
     */
    protected function buildPendingMessage(Penjualan $penjualan, string $appName, ?string $paymentUrl = null): string
    {
        $message = "🔔 *PEMBAYARAN BELUM SELESAI - " . strtoupper($appName) . " 🔔*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan Yth.') . "*,\n";
        $message .= "Pesanan Anda dengan nomor invoice *#" . $penjualan->invoice_number . "* telah berhasil dibuat.\n";
        $message .= "Total pembayaran yang harus Anda lakukan adalah: *Rp" . number_format($penjualan->total, 0, ',', '.') . "*.\n\n";

        $message .= "```\n";
        $message .= "--------------------------------------\n";
        $message .= "🧾 INVOICE #" . $penjualan->invoice_number . "\n";
        $message .= "Tanggal: " . Carbon::parse($penjualan->created_at)->translatedFormat('d M Y, H:i') . " WIB\n";
        $message .= "Tipe: " . ucwords(str_replace('_', ' ', $penjualan->type)) . "\n";
        if ($penjualan->type === 'dine_in' && $penjualan->meja) {
            $message .= "Meja: " . $penjualan->meja->nama . "\n";
        }
        $message .= "--------------------------------------\n";
        $message .= "🛒 Detail Pesanan:\n";
        foreach ($penjualan->details as $item) {
            $message .= sprintf(
                "%-20s %3dx Rp%-8s = Rp%s\n",
                Str::limit($item->produk->nama_produk, 20, ''),
                $item->qty,
                number_format($item->produk->harga_jual, 0, ',', '.'),
                number_format($item->subtotal_item, 0, ',', '.')
            );
        }
        $message .= "--------------------------------------\n";
        $message .= "💰 Ringkasan Pembayaran:\n";
        $message .= sprintf("%-25s Rp%s\n", "Sub Total:", number_format($penjualan->sub_total, 0, ',', '.'));
        if ($penjualan->ppn > 0) {
            $message .= sprintf("%-25s Rp%s\n", "PPN (" . $penjualan->ppn . "%):", number_format(($penjualan->sub_total * $penjualan->ppn / 100), 0, ',', '.'));
        }
        if ($penjualan->biaya_layanan > 0) {
            $message .= sprintf("%-25s Rp%s\n", "Biaya Layanan:", number_format($penjualan->biaya_layanan, 0, ',', '.'));
        }
        $message .= "--------------------------------------\n";
        $message .= sprintf("%-25s *Rp%s*\n", "Total Pembayaran:", number_format($penjualan->total, 0, ',', '.'));
        $message .= sprintf("%-25s %s\n", "Metode Pembayaran:", ucwords($penjualan->payment_method));
        $message .= "--------------------------------------\n";
        $message .= "```\n\n";

        if ($paymentUrl) {
            $message .= "🔗 *Klik link berikut untuk menyelesaikan pembayaran Anda:*\n";
            $message .= " " . $paymentUrl . "\n\n";
        }
        $expiryTime = Carbon::parse($penjualan->created_at)->addMinutes(120)->translatedFormat('H:i');
        $message .= "Mohon segera selesaikan pembayaran Anda sebelum pukul *" . $expiryTime . "* WIB. Setelah pembayaran berhasil, Anda akan menerima konfirmasi.\n\n";
        $message .= "Jika ada pertanyaan, jangan ragu menghubungi kami.\n";
        $message .= "Terima kasih!\n*" . $appName . "*";

        return $message;
    }

    /**
     * Builds a WhatsApp message for a pending cash payment.
     *
     * @param Penjualan $penjualan The Penjualan model instance.
     * @param string $appName The name of the application.
     * @return string
     */
    protected function buildCashPendingMessage(Penjualan $penjualan, string $appName): string
    {
        $message = "🔔 *PEMBAYARAN TUNAI BELUM SELESAI - " . strtoupper($appName) . " 🔔*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan Yth.') . "*,\n";
        $message .= "Pesanan Anda dengan nomor invoice *#" . $penjualan->invoice_number . "* telah berhasil dibuat.\n";
        $message .= "Total pembayaran yang harus Anda lakukan adalah: *Rp" . number_format($penjualan->total, 0, ',', '.') . "*.\n\n";

        $message .= "```\n";
        $message .= "--------------------------------------\n";
        $message .= "🧾 INVOICE #" . $penjualan->invoice_number . "\n";
        $message .= "Tanggal: " . Carbon::parse($penjualan->created_at)->translatedFormat('d M Y, H:i') . " WIB\n";
        $message .= "Tipe: " . ucwords(str_replace('_', ' ', $penjualan->type)) . "\n";
        if ($penjualan->type === 'dine_in' && $penjualan->meja) {
            $message .= "Meja: " . $penjualan->meja->nama . "\n";
        }
        $message .= "--------------------------------------\n";
        $message .= "🛒 Detail Pesanan:\n";
        foreach ($penjualan->details as $item) {
            $message .= sprintf(
                "%-20s %3dx Rp%-8s = Rp%s\n",
                Str::limit($item->produk->nama_produk, 20, ''),
                $item->qty,
                number_format($item->produk->harga_jual, 0, ',', '.'),
                number_format($item->subtotal_item, 0, ',', '.')
            );
        }
        $message .= "--------------------------------------\n";
        $message .= "💰 Ringkasan Pembayaran:\n";
        $message .= sprintf("%-25s Rp%s\n", "Sub Total:", number_format($penjualan->sub_total, 0, ',', '.'));
        if ($penjualan->ppn > 0) {
            $message .= sprintf("%-25s Rp%s\n", "PPN (" . $penjualan->ppn . "%):", number_format(($penjualan->sub_total * $penjualan->ppn / 100), 0, ',', '.'));
        }
        if ($penjualan->biaya_layanan > 0) {
            $message .= sprintf("%-25s Rp%s\n", "Biaya Layanan:", number_format($penjualan->biaya_layanan, 0, ',', '.'));
        }
        $message .= "--------------------------------------\n";
        $message .= sprintf("%-25s *Rp%s*\n", "Total Pembayaran:", number_format($penjualan->total, 0, ',', '.'));
        $message .= sprintf("%-25s %s\n", "Metode Pembayaran:", ucwords($penjualan->payment_method));
        $message .= "--------------------------------------\n";
        $message .= "```\n\n";

        $message .= "Mohon segera lakukan pembayaran tunai di kasir kami. Setelah pembayaran berhasil, Anda akan menerima konfirmasi.\n\n";
        $message .= "Jika ada pertanyaan, jangan ragu menghubungi kami.\n";
        $message .= "Terima kasih!\n*" . $appName . "*";

        return $message;
    }

    /**
     * Sends a WhatsApp message using a cURL-based gateway.
     *
     * @param string $number The recipient's phone number (e.g., '081234567890').
     * @param string $message The message content.
     * @return void
     */
    protected function sendWhatsAppMessage(string $number, string $message): void
    {
        $waNumber = preg_replace('/[^0-9]/', '', $number);

        if (empty($waNumber) || strlen($waNumber) < 9) {
            Log::warning("❌ WhatsApp message NOT sent: Invalid number. Input: {$number} | Cleaned: {$waNumber}");
            return;
        }

        $waGatewayUrl = env('APP_WA_URL');
        if (empty($waGatewayUrl)) {
            Log::warning("❌ WhatsApp message NOT sent: APP_WA_URL is not set in .env");
            return;
        }

        try {
            Log::info("📤 Sending WhatsApp to {$waNumber} with message:\n" . $message);

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $waGatewayUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => [
                    'message' => $message,
                    'to' => $waNumber,
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                Log::error("❌ Failed to send WA to {$waNumber}: cURL Error - {$err}");
            } elseif ($httpCode >= 400) {
                Log::error("❌ Failed to send WA to {$waNumber}: HTTP {$httpCode} - Response: {$response}");
            } else {
                Log::info("✅ WhatsApp sent to {$waNumber} | Response: {$response}");
            }
        } catch (\Exception $e) {
            Log::error("❌ Exception while sending WhatsApp to {$waNumber}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function rollbackStock($details): void
    {
        foreach ($details as $detail) {
            $produk = Produk::find($detail->produk_id);
            if ($produk) {
                $produk->increment('stok', $detail->qty);
                Log::info("Stok produk {$produk->nama_produk} dikembalikan sebanyak {$detail->qty}.");
            }
        }
    }
    public function checkDuitkuStatus(Request $request)
    {
        $request->validate([
            'invoiceNumber' => 'required|string|exists:penjualans,invoice_number',
        ]);

        try {
            $penjualan = Penjualan::where('invoice_number', $request->invoiceNumber)->firstOrFail();

            Log::info('Checking local sale status for Duitku transaction', [
                'invoice_number' => $request->invoiceNumber,
                'current_status_in_db' => $penjualan->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status pembayaran diperbarui.',
                'status_penjualan' => $penjualan->status,
                'invoice_number' => $penjualan->invoice_number,
            ]);
        } catch (Exception $e) {
            Log::error('Error checking local sale status: ' . $e->getMessage(), [
                'request_payload' => $request->all(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status pembayaran dari database: ' . $e->getMessage(),
            ], 500);
        }
    }
}
