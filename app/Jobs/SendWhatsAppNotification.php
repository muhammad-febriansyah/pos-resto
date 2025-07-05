<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http; // Penting: Gunakan Laravel HTTP Client

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phoneNumber;
    protected $message;

    /**
     * Create a new job instance.
     *
     * @param string $phoneNumber Nomor telepon penerima
     * @param string $message Konten pesan WhatsApp
     * @return void
     */
    public function __construct(string $phoneNumber, string $message)
    {
        $this->phoneNumber = $phoneNumber;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $waNumber = preg_replace('/[^0-9]/', '', $this->phoneNumber);

        if (empty($waNumber) || strlen($waNumber) < 9) {
            Log::warning("❌ WhatsApp message NOT sent (Job): Invalid number. Input: {$this->phoneNumber} | Cleaned: {$waNumber}");
            return;
        }

        $waGatewayUrl = env('APP_WA_URL');
        if (empty($waGatewayUrl)) {
            Log::error("❌ WhatsApp message NOT sent (Job): APP_WA_URL is not set in .env");
            return;
        }

        try {
            Log::info("📤 Sending WhatsApp (Job) to {$waNumber} with message:\n" . $this->message);

            // Menggunakan Laravel HTTP Client dengan timeout yang lebih longgar
            // Jika gateway WhatsApp sangat lambat, timeout ini bisa diatur lebih tinggi
            $response = Http::timeout(60)->post($waGatewayUrl, [
                'message' => $this->message,
                'to' => $waNumber,
            ]);

            if ($response->successful()) {
                Log::info("✅ WhatsApp sent (Job) to {$waNumber} | Response: " . $response->body());
            } else {
                Log::error("❌ Failed to send WA (Job) to {$waNumber}: HTTP {$response->status()} - Response: " . $response->body());
                // Opsional: Jika Anda ingin mencoba lagi (retry) job ini
                // Anda bisa melempar Exception di sini, dan Laravel akan mencoba kembali job sesuai konfigurasi queue
                // throw new \Exception("Failed to send WhatsApp message through gateway.");
            }
        } catch (\Exception $e) {
            Log::error("❌ Exception while sending WhatsApp (Job) to {$waNumber}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // Opsional: Re-throw exception agar job gagal dan bisa di-retry
            // throw $e;
        }
    }
}
