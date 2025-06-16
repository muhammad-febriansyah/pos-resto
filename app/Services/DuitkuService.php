<?php

namespace App\Services;

use App\Models\Produk; // Pastikan ini diimpor jika Anda ingin mengambil detail produk
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DuitkuService
{
    protected $apiKey;
    protected $merchantCode;
    protected $callbackUrl;
    protected $returnUrl;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = env('DUITKU_API_KEY');
        $this->merchantCode = env('DUITKU_MERCHANT_CODE');
        $this->callbackUrl = env('APP_URL') . '/duitku/callback';
        $this->returnUrl = env('APP_URL') . '/cashier';
        $this->baseUrl = env('DUITKU_BASE_URL', 'https://sandbox.duitku.com/webapi');

        if (empty($this->apiKey) || empty($this->merchantCode)) {
            Log::error('DuitkuService: DUITKU_API_KEY or DUITKU_MERCHANT_CODE is not set in .env file.');
            throw new \Exception('Duitku API Key or Merchant Code is not configured.');
        }

        Log::info('DuitkuService Initialized', [
            'merchantCode' => $this->merchantCode,
            'callbackUrl' => $this->callbackUrl,
            'returnUrl' => $this->returnUrl,
            'baseUrl' => $this->baseUrl,
        ]);
    }

    /**
     * Membuat invoice pembayaran Duitku dengan detail pelanggan dan item yang lengkap.
     *
     * @param float $totalAmount Total jumlah pembayaran
     * @param string $orderId Nomor invoice unik
     * @param array $customerData Array berisi nama, email, phone, dan (opsional) alamat pelanggan
     * Contoh: ['name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '08123...', 'address' => 'Jl. ABC', 'city' => 'Jakarta', 'postalCode' => '12345', 'countryCode' => 'ID']
     * @param array $cartItems Array dari item di keranjang belanja
     * Contoh: [['id' => 1, 'qty' => 2], ['id' => 2, 'qty' => 1]]
     * @return array Respon dari Duitku
     * @throws \Exception
     */
    public function createInvoice(float $totalAmount, string $orderId, array $customerData, array $cartItems): array
    {
        if ($totalAmount <= 0) {
            Log::error('DuitkuService: Invalid total amount provided.', ['amount' => $totalAmount, 'orderId' => $orderId]);
            return ['success' => false, 'message' => 'Invalid transaction amount. Total amount must be positive.'];
        }

        // --- Persiapan Customer Data ---
        $customerName = $customerData['name'] ?? 'Guest Customer';
        $customerEmail = filter_var($customerData['email'] ?? 'guest@example.com', FILTER_VALIDATE_EMAIL) ? $customerData['email'] : 'guest@example.com';
        $customerPhone = preg_replace('/[^0-9]/', '', $customerData['phone'] ?? '081234567890');
        if (empty($customerPhone)) {
            $customerPhone = '081234567890';
        }

        // Memisahkan nama depan dan nama belakang (contoh sederhana)
        $nameParts = explode(' ', $customerName, 2);
        $firstName = $nameParts[0] ?? 'Guest';
        $lastName = $nameParts[1] ?? 'Customer';

        // Persiapan Address (opsional, bisa disesuaikan dengan data yang Anda miliki)
        $addressDetail = [
            'firstName'   => $firstName,
            'lastName'    => $lastName,
            'address'     => $customerData['address'] ?? 'N/A',
            'city'        => $customerData['city'] ?? 'N/A',
            'postalCode'  => $customerData['postalCode'] ?? '00000',
            'phone'       => $customerPhone,
            'countryCode' => $customerData['countryCode'] ?? 'ID'
        ];

        $customerDetail = [
            'firstName'       => $firstName,
            'lastName'        => $lastName,
            'email'           => $customerEmail,
            'phoneNumber'     => $customerPhone,
            'billingAddress'  => $addressDetail,
            'shippingAddress' => $addressDetail // Bisa sama atau berbeda dengan billing address
        ];

        // --- Persiapan Item Details ---
        $itemDetails = [];
        $productDetailsString = []; // Untuk productDetails utama

        foreach ($cartItems as $cartItem) {
            $produk = Produk::find($cartItem['id']);
            if ($produk) {
                $itemDetails[] = [
                    'name'     => $produk->nama_produk,
                    'price'    => $produk->harga_jual,
                    'quantity' => $cartItem['qty']
                ];
                $productDetailsString[] = $produk->nama_produk . ' (x' . $cartItem['qty'] . ')';
            }
        }
        $productDetails = count($productDetailsString) > 0 ? implode(', ', $productDetailsString) : 'Pembayaran Pesanan';
        if (strlen($productDetails) > 255) { // Batasi panjang string jika terlalu panjang
            $productDetails = substr($productDetails, 0, 252) . '...';
        }

        // --- Generate Signature ---
        $signature = md5($this->merchantCode . $orderId . $totalAmount . $this->apiKey);

        // --- Parameter untuk Duitku API ---
        $params = [
            'merchantCode'    => $this->merchantCode,
            'paymentAmount'   => $totalAmount,
            'merchantOrderId' => $orderId,
            'productDetails'  => $productDetails,
            'email'           => $customerEmail,
            'phoneNumber'     => $customerPhone,
            'customerVaName'  => $customerName,
            'callbackUrl'     => $this->callbackUrl,
            'returnUrl'       => $this->returnUrl . '?status={status}&reference={reference}&merchantOrderId={merchantOrderId}',
            'signature'       => $signature,
            'expiryPeriod'    => 120, // 2 jam
            'paymentMethod'   => '', // Kosongkan agar semua metode pembayaran ditampilkan
            'customerDetail'  => $customerDetail,
            'itemDetails'     => $itemDetails,
            // 'additionalParam' => '', // Opsional jika tidak ada
            // 'merchantUserInfo' => '', // Opsional jika tidak ada
        ];

        Log::info('DuitkuService: Creating invoice with parameters (partial):', [
            'merchantOrderId' => $orderId,
            'paymentAmount' => $totalAmount,
            'email' => $customerEmail,
            'phoneNumber' => $customerPhone,
            'signature' => $signature,
            'productDetails' => $productDetails,
            'customerDetail' => $customerDetail, // Log customerDetail lengkap
            'itemDetails' => $itemDetails, // Log itemDetails lengkap
        ]);

        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/api/merchant/createInvoice", $params);

            Log::info('Duitku Create Invoice Raw Response:', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['paymentUrl']) && $data['paymentUrl']) {
                    Log::info('Duitku Create Invoice Success:', ['paymentUrl' => $data['paymentUrl'], 'reference' => $data['reference'] ?? null]);
                    return [
                        'success' => true,
                        'paymentUrl' => $data['paymentUrl'],
                        'reference' => $data['reference'] ?? null,
                        'message' => 'Invoice created successfully.',
                    ];
                } else {
                    $errorMessage = $data['message'] ?? 'Failed to get payment URL from Duitku. No specific message provided.';
                    Log::error('Duitku Create Invoice: No paymentUrl in successful response.', [
                        'response_data' => $data,
                        'orderId' => $orderId,
                        'error_message' => $errorMessage
                    ]);
                    return [
                        'success' => false,
                        'message' => $errorMessage,
                        'data' => $data,
                    ];
                }
            } else {
                $errorMessage = 'Failed to create Duitku invoice. HTTP Status: ' . $response->status();
                $responseBody = $response->json();
                if (isset($responseBody['message'])) {
                    $errorMessage .= ' Duitku Message: ' . $responseBody['message'];
                } else {
                    $errorMessage .= ' Raw Body: ' . $response->body();
                }
                Log::error('Duitku Create Invoice Failed:', [
                    'orderId' => $orderId,
                    'http_status' => $response->status(),
                    'response_body' => $responseBody,
                    'error_message' => $errorMessage
                ]);
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'status_code' => $response->status(),
                    'data' => $responseBody,
                ];
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('DuitkuService createInvoice Connection Error: ' . $e->getMessage(), ['exception' => $e, 'orderId' => $orderId]);
            return [
                'success' => false,
                'message' => 'Failed to connect to Duitku API: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('DuitkuService createInvoice General Error: ' . $e->getMessage(), ['exception' => $e, 'orderId' => $orderId]);
            return [
                'success' => false,
                'message' => 'An unexpected error occurred when trying to create Duitku invoice: ' . $e->getMessage(),
            ];
        }
    }

    // Metode getPaymentMethods dan generateSignature tidak berubah jika tidak ada kebutuhan spesifik.
    // Jika Anda ingin menggunakan generateSignature untuk createInvoice dengan 3 parameter,
    // maka gunakan md5($merchantCode . $merchantOrderId . $amount . $this->apiKey); seperti sebelumnya.
    // Untuk getPaymentMethod, signature hanya menggunakan merchantCode dan amount.
    // Saya telah merestrukturisasi generateSignature ke dalam createInvoice langsung untuk kejelasan.
}
