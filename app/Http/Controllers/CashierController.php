<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\Penjualan;
use App\Models\DetailPenjualan;
use App\Models\Kategori;
use App\Models\Meja;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Notification;


class CashierController extends Controller
{
    public function __construct()
    {
        // Set Midtrans configuration from environment variables
        Config::$serverKey = env('MIDTRANS_SERVERKEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false); // Default to false for safety
        Config::$isSanitized = true; // Always sanitize to prevent injection
        Config::$is3ds = true; // Enable 3D Secure for enhanced security
    }

    public function index()
    {
        $products = Produk::where('is_active', true)->get();
        $mejas = Meja::where('status', 'tersedia')->get();
        $customers = User::where('role', 'customer')->get();
        $settings = Setting::first();
        $kategoris = Kategori::all();
        $latestTransactions = Penjualan::with(['customer', 'meja', 'details', 'user'])
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at', 'desc')
            ->get();


        return Inertia::render('Cashier/Index', [
            'products' => $products,
            'mejas' => $mejas,
            'customers' => $customers,
            'settings' => $settings,
            'kategoris' => $kategoris,
            'latestTransactions' => $latestTransactions,
        ]);
    }

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
            'amountPaid' => 'nullable|numeric|min:0', // Tambahkan validasi untuk amountPaid
        ]);

        DB::beginTransaction();
        $selectedMeja = null;
        try {
            $settings = Setting::firstOrFail();
            $ppnPercentage = $settings->ppn;
            $biayaLayananDefault = $settings->biaya_lainnya;
            $namaweb = $settings->site_name ?? 'Restoran Anda';

            $subTotal = 0;
            $laba = 0;
            $midtransItemDetails = [];

            foreach ($request->cartItems as $item) {
                $produk = Produk::findOrFail($item['id']);

                if ($produk->stok < $item['qty']) {
                    throw new Exception("Stok produk '{$produk->nama_produk}' tidak cukup.");
                }

                $itemPrice = $produk->harga_jual;
                $itemQuantity = $item['qty'];

                $subTotal += $itemPrice * $itemQuantity;
                $laba += ($produk->harga_jual - $produk->harga_beli) * $itemQuantity;

                // Decrement stock immediately
                $produk->decrement('stok', $itemQuantity);

                $midtransItemDetails[] = [
                    'id' => (string) $produk->id,
                    'price' => (int) round($itemPrice),
                    'quantity' => (int) $itemQuantity,
                    'name' => $produk->nama_produk,
                ];
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
                'user_id' => Auth::user()->id ?? 1, // Fallback to user ID 1 if not authenticated (e.g., for testing)
                'customer_id' => $request->customerId,
                'meja_id' => $request->mejaId,
                'payment_method' => $request->paymentMethod,
                'type' => $request->type,
                'sub_total' => $subTotal,
                'ppn' => $ppnPercentage,
                'biaya_layanan' => $biayaLayanan ?? 0,
                'total' => $total,
                'laba' => $laba,
                'status' => 'pending', // Initial status
            ];

            if ($request->paymentMethod === 'cash') {
                // Set status langsung menjadi 'paid' jika pembayaran tunai
                $penjualanData['status'] = 'paid';

                // Ambil amountPaid dari request, default ke total jika tidak ada
                $amountPaid = $request->amountPaid ?? $total;
                // Hitung kembalian
                $change = $amountPaid - $total;

                if ($amountPaid < $total) {
                    throw new Exception("Jumlah uang tunai yang dibayarkan kurang dari total pembayaran.");
                }

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

                DB::commit();

                $penjualan->load(['customer', 'meja', 'details.produk']);
                $customerPhoneNumber = $penjualan->customer->phone ?? env('DEFAULT_CUSTOMER_PHONE', null);

                if ($customerPhoneNumber) {
                    // Gunakan buildInvoiceMessage yang sudah mencakup detail pembayaran
                    $message = $this->buildInvoiceMessage($penjualan, $namaweb, $amountPaid, $change);
                    $this->sendWhatsAppMessage($customerPhoneNumber, $message);
                } else {
                    Log::warning("WhatsApp not sent: Customer phone number not found for invoice {$invoiceNumber}. (Cash)");
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan tunai berhasil dibuat dan dibayar.',
                    'invoice' => $penjualan,
                    'status_penjualan' => 'paid', // Mengubah status di respons
                    'amountPaid' => $amountPaid,
                    'change' => $change,
                ]);
            }

            if ($request->paymentMethod === 'midtrans') {
                if ($total <= 0) {
                    throw new Exception("Total pembayaran harus lebih dari nol untuk pembayaran Midtrans.");
                }

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

                try {
                    $customer = $request->customerId ? User::find($request->customerId) : Auth::user();
                    $customerName = $customer?->name ?? 'Pelanggan';
                    $customerEmail = filter_var($customer?->email ?? 'guest@example.com', FILTER_VALIDATE_EMAIL) ? $customer->email : 'guest@example.com';
                    $customerPhone = preg_replace('/[^0-9]/', '', $customer?->phone ?? '081234567890');

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

                    $penjualan->update(['total' => $grossAmount]); // Update total based on what Midtrans will receive

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

                    $appUrl = env('APP_URL');
                    $finish_redirect_url = $appUrl . "/cashier?status=success&order_id={$invoiceNumber}";
                    $error_redirect_url = $appUrl . "/cashier?status=error&order_id={$invoiceNumber}";
                    $pending_redirect_url = $appUrl . "/cashier?status=pending&order_id={$invoiceNumber}";

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
                            'duration' => 120, // 2 hours expiry
                        ],
                    ];

                    Log::info('Midtrans Snap Parameters', [
                        'params' => $params,
                        'penjualan_id' => $penjualan->id
                    ]);

                    $snapResponse = Snap::createTransaction($params);
                    $snapToken = $snapResponse->token;
                    $redirectUrl = $snapResponse->redirect_url;

                    $penjualan->update([
                        'midtrans_snap_token' => $snapToken,
                        'payment_url' => $redirectUrl,
                    ]);

                    DB::commit();

                    $penjualan->load(['customer', 'meja', 'details.produk']);
                    $customerPhoneNumber = $penjualan->customer->phone ?? env('DEFAULT_CUSTOMER_PHONE', null);

                    if ($customerPhoneNumber) {
                        $message = $this->buildMidtransPendingMessage($penjualan, $namaweb, $redirectUrl);
                        $this->sendWhatsAppMessage($customerPhoneNumber, $message);
                    } else {
                        Log::warning("WhatsApp not sent: Customer phone number not found for invoice {$invoiceNumber}. (Midtrans)");
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Transaksi Midtrans berhasil dibuat. Mengarahkan ke pembayaran Midtrans...',
                        'snapToken' => $snapToken,
                        'invoiceNumber' => $invoiceNumber,
                        'redirectUrl' => $redirectUrl,
                        'status_penjualan' => 'pending',
                    ]);
                } catch (Exception $e) {
                    DB::rollBack();
                    if ($selectedMeja && $selectedMeja->status === 'dipakai') {
                        $selectedMeja->status = 'tersedia';
                        $selectedMeja->save();
                    }
                    Log::error('Gagal membuat transaksi Midtrans: ' . $e->getMessage(), [
                        'params' => $params ?? 'N/A',
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal membuat invoice Midtrans: ' . $e->getMessage(),
                    ], 500);
                }
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

    public function printInvoice(Penjualan $penjualan)
    {
        $setting = Setting::first();
        $penjualan->load(['customer', 'meja', 'details.produk', 'user']);
        return view('penjualan.print_invoice', compact('penjualan', 'setting'));
    }


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

        if ($amountPaid !== null && $penjualan->payment_method === 'cash') {
            $message .= sprintf("%-25s Rp%s\n", "Uang Dibayar:", number_format($amountPaid, 0, ',', '.'));
            $message .= sprintf("%-25s Rp%s\n", "Kembalian:", number_format($change, 0, ',', '.'));
        }
        $message .= "--------------------------------------\n";
        $message .= "```\n\n";

        $message .= "Pembayaran Anda telah *berhasil* diterima. Kami akan segera memproses pesanan Anda.\n";
        $message .= "Terima kasih telah berbelanja di *" . $appName . "*! Kami menantikan kunjungan Anda kembali 😊\n\n";
        $message .= "© " . Carbon::now()->year . " *" . $appName . "*";

        return $message;
    }

    protected function buildCashPendingMessage(Penjualan $penjualan, string $appName): string
    {
        $message = "Halo " . ($penjualan->customer->name ?? 'Pelanggan') . "!\n";
        $message .= "Pesanan Anda dengan nomor invoice *{$penjualan->invoice_number}* telah berhasil dibuat.\n";
        $message .= "Total yang harus dibayar: *Rp " . number_format($penjualan->total, 0, ',', '.') . "*.\n";
        $message .= "Silakan lakukan pembayaran di kasir kami. Terima kasih telah berbelanja di {$appName}!";
        return $message;
    }

    // Mengubah fungsi buildMidtransPendingMessage
    protected function buildMidtransPendingMessage(Penjualan $penjualan, string $appName, ?string $paymentLink = null): string
    {
        $message = "⏳ *PEMBAYARAN TERTUNDA - " . strtoupper($appName) . " ⏳*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan Yth.') . "*,\n";
        $message .= "Pesanan Anda dengan nomor invoice *#" . $penjualan->invoice_number . "* telah berhasil dibuat.\n";
        $message .= "Mohon segera selesaikan pembayaran Anda.\n\n";

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

        if ($paymentLink) {
            $message .= "Klik tautan ini untuk melanjutkan pembayaran Anda: 👇\n";
            $message .= "*{$paymentLink}*\n\n";
        } else {
            $message .= "Silakan hubungi kasir kami untuk petunjuk pembayaran lebih lanjut.\n\n";
        }

        $message .= "Terima kasih telah berbelanja di *" . $appName . "*! Kami menantikan kunjungan Anda kembali 😊\n\n";
        $message .= "© " . Carbon::now()->year . " *" . $appName . "*";

        return $message;
    }

    protected function buildMidtransSuccessMessage(Penjualan $penjualan, string $appName): string
    {
        $message = "✅ *PEMBAYARAN BERHASIL - " . strtoupper($appName) . " ✅*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan Yth.') . "*,\n";
        $message .= "Pembayaran Anda untuk pesanan dengan nomor invoice *#" . $penjualan->invoice_number . "* telah *berhasil* kami terima!\n";
        $message .= "Terima kasih atas pembayaran Anda senilai: *Rp" . number_format($penjualan->total, 0, ',', '.') . "*.\n\n";

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

        $message .= "Pesanan Anda akan segera diproses. Jika ada pertanyaan atau butuh bantuan, jangan sungkan untuk menghubungi kami.\n";
        $message .= "Terima kasih banyak atas kepercayaan Anda pada *" . $appName . "*! Sampai jumpa kembali 😊\n\n";
        $message .= "© " . Carbon::now()->year . " *" . $appName . "*";

        return $message;
    }

    protected function sendWhatsAppMessage(string $number, string $message): void
    {
        $waNumber = preg_replace('/[^0-9]/', '', $number);

        if (empty($waNumber) || strlen($waNumber) < 9) {
            Log::warning("❌ WhatsApp message NOT sent: Invalid phone number. Input: {$number} | Cleaned: {$waNumber}");
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
}
