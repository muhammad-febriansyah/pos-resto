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
use Duitku\Config as DuitkuConfig;
use Duitku\Invoice;
use Duitku\Pop;
use Duitku\Config; // Pastikan Anda sudah mengimpor Duitku\Config
use Exception;
use Hamcrest\Core\HasToString;

class CashierController extends Controller
{
    public function index()
    {
        $products = Produk::where('is_active', true)->get();
        $mejas = Meja::where('status', 'tersedia')->get();
        $customers = User::where('role', 'customer')->get();
        $settings = Setting::first();
        $kategoris = Kategori::all();
        $latestTransactions = Penjualan::with(['customer', 'meja', 'details', 'user'])
            ->whereDate('created_at', Carbon::today()) // Hanya transaksi hari ini
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
            'paymentMethod' => 'required|string|in:cash,duitku',
            'amountPaid' => 'nullable|numeric|min:0',
            'customerId' => 'nullable|exists:users,id',
            'mejaId' => 'nullable|exists:mejas,id',
            'type' => 'required|string|in:dine_in,take_away,delivery',
        ]);

        DB::beginTransaction();
        try {
            $settings = Setting::firstOrFail();
            $ppnPercentage = $settings->ppn;
            $biayaLayananDefault = $settings->biaya_layanan_default;
            $namaweb = $settings->site_name ?? 'Restoran Anda';

            $subTotal = 0;
            $laba = 0;
            $cartItemsData = [];

            foreach ($request->cartItems as $item) {
                $produk = Produk::findOrFail($item['id']);

                if ($produk->stok < $item['qty']) {
                    throw new \Exception("Stok produk '{$produk->nama_produk}' tidak mencukupi. Tersedia: {$produk->stok}, Diminta: {$item['qty']}.");
                }

                $subTotal += $produk->harga_jual * $item['qty'];
                $laba += ($produk->harga_jual - $produk->harga_beli) * $item['qty'];

                $produk->decrement('stok', $item['qty']);

                $cartItemsData[] = [
                    'nama_produk' => $produk->nama_produk,
                    'qty' => $item['qty'],
                    'harga_jual' => $produk->harga_jual,
                ];
            }

            $ppnAmount = ($subTotal * $ppnPercentage) / 100;
            $biayaLayanan = $request->type === 'dine_in' ? $biayaLayananDefault : 0;
            $total = $subTotal + $ppnAmount + $biayaLayanan;

            $invoiceNumber = 'INV' . date('Ymd') . strtoupper(Str::random(6));

            $selectedMeja = null;
            if ($request->type === 'dine_in' && $request->mejaId) {
                $selectedMeja = Meja::find($request->mejaId);
                if ($selectedMeja) {
                    if ($selectedMeja->status !== 'tersedia') {
                        throw new \Exception("Meja {$selectedMeja->nama} saat ini tidak tersedia.");
                    }
                    $selectedMeja->status = 'dipakai';
                    $selectedMeja->save();
                }
            }

            $penjualanData = [
                'invoice_number' => $invoiceNumber,
                'user_id' => Auth::id(),
                'customer_id' => $request->customerId,
                'meja_id' => $request->mejaId,
                'payment_method' => $request->paymentMethod,
                'type' => $request->type,
                'sub_total' => $subTotal,
                'ppn' => $ppnPercentage,
                'biaya_layanan' => $biayaLayanan ?? 0,
                'total' => $total,
                'laba' => $laba,
            ];

            if ($request->paymentMethod === 'cash') {
                $amountPaid = $request->amountPaid;
                $change = $amountPaid - $total;

                if ($amountPaid < $total) {
                    throw new \Exception("Uang pembayaran tidak mencukupi. Dibutuhkan: " . number_format($total, 0, ',', '.') . ", Diberikan: " . number_format($amountPaid, 0, ',', '.') . ".");
                }

                $penjualanData['status'] = 'paid';
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

                $penjualan->load(['customer', 'meja']);
                if ($penjualan->customer && $penjualan->customer->phone) {
                    $message = $this->buildInvoiceMessage($penjualan, $cartItemsData, $namaweb, $amountPaid, $change);
                    $this->sendWhatsAppMessage($penjualan->customer->phone, $message);
                } else {
                    Log::info("WhatsApp message not sent (Cash): Customer phone number not available or customer not selected.");
                }

                $penjualan->load(['details.produk', 'customer', 'meja']);
                return response()->json([
                    'success' => true,
                    'message' => 'Pembayaran tunai berhasil!',
                    'invoice' => $penjualan->toArray(),
                    'change' => $change,
                ]);
            }

            if ($request->paymentMethod === 'duitku') {
                if ($total <= 0) {
                    throw new \Exception("Total pembayaran harus lebih dari nol untuk pembayaran Duitku.");
                }

                try {
                    $duitkuApiKey = env("DUITKU_API_KEY");
                    $duitkuMerchantKey = env("DUITKU_MERCHANT_KEY");
                    $isSandbox = env('DUITKU_IS_SANDBOX', true);

                    if (!$duitkuApiKey || !$duitkuMerchantKey) {
                        throw new \Exception("DUITKU_API_KEY atau DUITKU_MERCHANT_KEY belum diset di .env.");
                    }

                    $duitkuConfig = new DuitkuConfig($duitkuApiKey, $duitkuMerchantKey);
                    $duitkuConfig->setSandboxMode($isSandbox);
                    $duitkuConfig->setSanitizedMode(true);
                    $duitkuConfig->setDuitkuLogs(true);

                    $customer = $request->customerId ? User::find($request->customerId) : null;
                    $customerName = $customer?->name ?? 'Guest Customer';
                    $customerEmail = filter_var($customer?->email ?? 'guest@example.com', FILTER_VALIDATE_EMAIL) ? $customer->email : 'guest@example.com';
                    $customerPhone = preg_replace('/[^0-9]/', '', $customer?->phone ?? '081234567890');
                    $customerAddress = trim(preg_replace('/\s+/', ' ', $customer?->address ?? 'Alamat tidak diketahui'));
                    $city = $customer?->city ?? 'N/A';
                    $postalCode = $customer?->postal_code ?? '00000';

                    $nameParts = explode(' ', $customerName, 2);
                    $firstName = $nameParts[0] ?? 'Guest';
                    $lastName = $nameParts[1] ?? 'Customer';

                    $itemDetails = [];
                    $productDetailsList = [];

                    foreach ($request->cartItems as $item) {
                        $produk = Produk::findOrFail($item['id']);
                        $price = (int) $produk->harga_jual;
                        $qty = $item['qty'];
                        $itemDetails[] = [
                            'name' => $produk->nama_produk,
                            'price' => $price,
                            'quantity' => $qty
                        ];
                        $productDetailsList[] = $produk->nama_produk . " (x{$qty})";
                    }

                    if ($ppnAmount > 0) {
                        $itemDetails[] = [
                            'name' => 'PPN',
                            'price' => (int) round($ppnAmount),
                            'quantity' => 1
                        ];
                        $productDetailsList[] = 'PPN';
                    }

                    if ($biayaLayanan > 0) {
                        $itemDetails[] = [
                            'name' => 'Biaya Layanan',
                            'price' => (int) round($biayaLayanan),
                            'quantity' => 1
                        ];
                        $productDetailsList[] = 'Biaya Layanan';
                    }

                    $productDetails = substr(implode(', ', $productDetailsList), 0, 255);

                    $callbackUrl = env('APP_URL') . '/duitku/callback';
                    $returnUrl = env('APP_URL') . '/cashier?status={status}&reference={reference}&merchantOrderId={merchantOrderId}';
                    $expiryPeriod = 120;

                    $params = [
                        'paymentAmount'     => (int) round($total),
                        'merchantOrderId'   => $invoiceNumber,
                        'productDetails'    => $productDetails,
                        'additionalParam'   => '',
                        'merchantUserInfo'  => '',
                        'customerVaName'    => $customerName,
                        'email'             => $customerEmail,
                        'phoneNumber'       => $customerPhone,
                        'itemDetails'       => $itemDetails,
                        'customerDetail'    => [
                            'firstName'         => $firstName,
                            'lastName'          => $lastName,
                            'email'             => $customerEmail,
                            'phoneNumber'       => $customerPhone,
                            'billingAddress'    => [
                                'firstName'     => $firstName,
                                'lastName'      => $lastName,
                                'address'       => $customerAddress,
                                'city'          => $city,
                                'postalCode'    => $postalCode,
                                'phone'         => $customerPhone,
                                'countryCode'   => 'ID'
                            ],
                            'shippingAddress'   => [
                                'firstName'     => $firstName,
                                'lastName'      => $lastName,
                                'address'       => $customerAddress,
                                'city'          => $city,
                                'postalCode'    => $postalCode,
                                'phone'         => $customerPhone,
                                'countryCode'   => 'ID'
                            ]
                        ],
                        'callbackUrl'       => $callbackUrl,
                        'returnUrl'         => $returnUrl,
                        'expiryPeriod'      => $expiryPeriod
                    ];

                    $responseDuitkuPop = Pop::createInvoice($params, $duitkuConfig);
                    $data = json_decode($responseDuitkuPop, true);

                    $penjualanData['status'] = 'pending';
                    $penjualanData['duitku_reference'] = $data['reference'] ?? null;
                    $penjualanData['payment_url'] = $data['paymentUrl'] ?? null;
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

                    $penjualan->load(['customer', 'meja']);
                    if ($penjualan->customer && $penjualan->customer->phone) {
                        $message = $this->buildDuitkuPendingMessage($penjualan, $cartItemsData, $namaweb, $data['paymentUrl'] ?? null);
                        $this->sendWhatsAppMessage($penjualan->customer->phone, $message);
                    } else {
                        Log::info("WhatsApp message not sent (Duitku Pending): Customer phone number not available or customer not selected.");
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Invoice berhasil dibuat. Mengarahkan ke pembayaran Duitku...',
                        'paymentUrl' => $data['paymentUrl'] ?? null,
                        'invoiceNumber' => $invoiceNumber,
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Gagal membuat invoice: ' . $e->getMessage(),
                    ], 500);
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();

            if ($selectedMeja && $selectedMeja->status === 'dipakai') {
                $selectedMeja->status = 'tersedia';
                $selectedMeja->save();
            }

            Log::error('Error processing sale: ' . $e->getMessage(), [
                'request' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    protected function buildInvoiceMessage(Penjualan $penjualan, array $cartItemsData, string $appName, ?float $amountPaid = null, ?float $change = null): string
    {
        $message = "🌟 *INVOICE PEMBELIAN - " . strtoupper($appName) . " 🌟*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan') . "*,\n";
        $message .= "Terima kasih telah berbelanja di kami! Berikut rincian pesanan Anda:\n\n";

        $message .= "🧾 *Detail Pesanan:*\n";
        $message .= "  Kode Invoice: *#" . $penjualan->invoice_number . "*\n";
        $message .= "  Tanggal: " . Carbon::parse($penjualan->created_at)->translatedFormat('d M Y, H:i') . " WIB\n";
        $message .= "  Tipe Transaksi: " . ucwords(str_replace('_', ' ', $penjualan->type)) . "\n";
        if ($penjualan->type === 'dine_in' && $penjualan->meja) {
            $message .= "  Meja: " . $penjualan->meja->nama . "\n";
        }
        $message .= "  Status: *" . ucwords($penjualan->status) . "*\n";
        $message .= "--------------------------------------\n";

        $message .= "🛒 *Item Pesanan:*\n";
        foreach ($cartItemsData as $item) {
            $message .= "  • " . $item['nama_produk'] . " (x" . $item['qty'] . ") = Rp" . number_format($item['harga_jual'] * $item['qty'], 0, ',', '.') . "\n";
        }
        $message .= "--------------------------------------\n";

        $message .= "💰 *Ringkasan Pembayaran:*\n";
        $message .= "  Sub Total: Rp" . number_format($penjualan->sub_total, 0, ',', '.') . "\n";
        if ($penjualan->ppn > 0) {
            $message .= "  PPN (" . $penjualan->ppn . "%): Rp" . number_format(($penjualan->sub_total * $penjualan->ppn / 100), 0, ',', '.') . "\n";
        }
        if ($penjualan->biaya_layanan > 0) {
            $message .= "  Biaya Layanan: Rp" . number_format($penjualan->biaya_layanan, 0, ',', '.') . "\n";
        }
        $message .= "*Total Pembayaran: Rp" . number_format($penjualan->total, 0, ',', '.') . "*\n";
        $message .= "Metode Pembayaran: " . ucwords($penjualan->payment_method) . "\n";

        if ($amountPaid !== null && $penjualan->payment_method === 'cash') {
            $message .= "Uang Dibayar: Rp" . number_format($amountPaid, 0, ',', '.') . "\n";
            $message .= "Kembalian: Rp" . number_format($change, 0, ',', '.') . "\n";
        }
        $message .= "--------------------------------------\n";

        $message .= "Terima kasih telah berbelanja di *" . $appName . "*!\n";
        $message .= "Sampai jumpa kembali 😊\n\n";
        $message .= "© " . Carbon::now()->year . " *" . $appName . "*";

        return $message;
    }

    protected function buildDuitkuPendingMessage(Penjualan $penjualan, array $cartItemsData, string $appName, ?string $paymentUrl = null): string
    {
        $message = "⚠️ *PEMBAYARAN BELUM SELESAI - " . strtoupper($appName) . " ⚠️*\n\n";
        $message .= "Halo *" . ($penjualan->customer->name ?? 'Pelanggan') . "*,\n";
        $message .= "Pesanan Anda dengan kode invoice *#" . $penjualan->invoice_number . "* telah dibuat.\n";
        $message .= "Kami menunggu pembayaran Anda senilai: *Rp" . number_format($penjualan->total, 0, ',', '.') . "*.\n\n";

        $message .= "🧾 *Detail Pesanan:*\n";
        $message .= "  Kode Invoice: *#" . $penjualan->invoice_number . "*\n";
        $message .= "  Tanggal: " . Carbon::parse($penjualan->created_at)->translatedFormat('d M Y, H:i') . " WIB\n";
        $message .= "  Tipe Transaksi: " . ucwords(str_replace('_', ' ', $penjualan->type)) . "\n";
        if ($penjualan->type === 'dine_in' && $penjualan->meja) {
            $message .= "  Meja: " . $penjualan->meja->nama . "\n";
        }
        $message .= "--------------------------------------\n";

        $message .= "🛒 *Item Pesanan:*\n";
        foreach ($cartItemsData as $item) {
            $message .= "  • " . $item['nama_produk'] . " (x" . $item['qty'] . ") = Rp" . number_format($item['harga_jual'] * $item['qty'], 0, ',', '.') . "\n";
        }
        $message .= "--------------------------------------\n";

        if ($paymentUrl) {
            $message .= "🔗 *Klik link berikut untuk menyelesaikan pembayaran Anda:*\n";
            $message .= " " . $paymentUrl . "\n\n";
        }
        $expiryTime = Carbon::parse($penjualan->created_at)->addMinutes(120)->translatedFormat('H:i');
        $message .= "Mohon segera selesaikan pembayaran Anda sebelum *" . $expiryTime . "* WIB.\n";
        $message .= "Setelah pembayaran berhasil, Anda akan menerima konfirmasi.\n\n";
        $message .= "Terima kasih!\n*" . $appName . "*";

        return $message;
    }

    protected function sendWhatsAppMessage(string $number, string $message): void
    {
        $waNumber = preg_replace('/[^0-9]/', '', $number);

        if (empty($waNumber) || strlen($waNumber) < 9) {
            Log::warning("WhatsApp message not sent: Invalid or empty phone number provided for customer. Number: {$number}");
            return;
        }

        if (!env('APP_WA_URL')) {
            Log::warning("WhatsApp Gateway URL (APP_WA_URL) not set in .env. Skipping WhatsApp message.");
            return;
        }

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => env('APP_WA_URL'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => array('message' => $message, 'number' => $waNumber),
            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                Log::error("cURL Error for WhatsApp Gateway: " . $err);
            } else {
                Log::info("WhatsApp Gateway Response for number {$waNumber}: " . $response);
            }
        } catch (\Exception $e) {
            Log::error("Error sending WhatsApp message: " . $e->getMessage());
        }
    }

    public function printInvoice(Penjualan $penjualan)
    {
        $setting = Setting::first();
        $penjualan->load(['customer', 'meja', 'details.produk', 'user']);
        return view('penjualan.print_invoice', compact('penjualan', 'setting'));
    }

    public function callback(Request $request)
    {
        try {
            $duitkuConfig = new \Duitku\Config(env("DUITKU_API_KEY"), env("DUITKU_MERCHANT_KEY"));
            $duitkuConfig->setSandboxMode(env('DUITKU_IS_SANDBOX', true));

            $rawCallback = \Duitku\Pop::callback($duitkuConfig);

            if (is_array($rawCallback)) {
                $rawCallback = $rawCallback[0];
            }

            $notif = json_decode($rawCallback);

            $penjualan = \App\Models\Penjualan::without(['user', 'customer', 'meja', 'details'])
                ->where('invoice_number', $notif->merchantOrderId)
                ->first();

            if (!$penjualan) {
                return response()->json(['status' => 'NOT_FOUND', 'message' => 'Invoice tidak ditemukan'], 404);
            }

            if ($notif->resultCode === "00") {
                $penjualan->status = 'paid';
            } elseif ($notif->resultCode === "01") {
                $penjualan->status = 'failed';
            } else {
                $penjualan->status = 'unknown';
            }

            if ($penjualan->isDirty('status')) {
                $penjualan->save();
            }

            return response()->json(['status' => 'OK', 'message' => 'Callback processed']);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'ERROR', 'message' => $e->getMessage()], 500);
        }
    }
}
