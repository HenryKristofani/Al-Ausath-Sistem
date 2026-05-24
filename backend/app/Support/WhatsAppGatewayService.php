<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Gateway Service
 *
 * Mendukung provider Fonnte (fonnte.com) yang banyak digunakan di Indonesia.
 * Konfigurasi via .env:
 *   WHATSAPP_GATEWAY_PROVIDER=fonnte   # fonnte | disabled
 *   WHATSAPP_GATEWAY_TOKEN=YOUR_TOKEN
 *   WHATSAPP_SENDER_NUMBER=           # opsional, jika provider butuh
 */
class WhatsAppGatewayService
{
    protected string $provider;
    protected string $token;

    public function __construct()
    {
        $this->provider = strtolower((string) config('services.whatsapp.provider', 'disabled'));
        $this->token = (string) config('services.whatsapp.token', '');
    }

    /**
     * Kirim pesan WhatsApp ke nomor tertentu.
     *
     * @param  string  $phone   Nomor tujuan (format Indonesia, misal: 08xxxxxxxxxx atau 628xxxxxxxxxx)
     * @param  string  $message Isi pesan teks
     * @return bool    true jika berhasil, false jika gagal/disabled
     */
    public function send(string $phone, string $message): bool
    {
        if ($this->provider === 'disabled' || empty($this->token)) {
            Log::debug("[WhatsApp] Provider disabled atau token kosong. Pesan tidak dikirim.", ['phone' => $phone]);
            return false;
        }

        $phone = $this->normalizePhone($phone);

        try {
            return match ($this->provider) {
                'fonnte' => $this->sendViaFonnte($phone, $message),
                default  => $this->sendViaFonnte($phone, $message),
            };
        } catch (\Throwable $e) {
            Log::error("[WhatsApp] Gagal mengirim pesan ke {$phone}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template: Notifikasi tagihan SPP baru.
     */
    public function sendTagihanSpp(string $phone, string $namaSantri, string $bulan, float $nominal): bool
    {
        $nominalFormatted = 'Rp ' . number_format($nominal, 0, ',', '.');
        $message = "Assalamu'alaikum 🌙\n\n"
            . "Yth. Wali Santri *{$namaSantri}*,\n\n"
            . "Tagihan *SPP Bulan {$bulan}* sebesar *{$nominalFormatted}* telah diterbitkan oleh sistem Pondok Pesantren Al-Ausath.\n\n"
            . "Harap segera melakukan pembayaran. Terima kasih 🙏";

        return $this->send($phone, $message);
    }

    /**
     * Template: Konfirmasi pembayaran berhasil diverifikasi.
     */
    public function sendKonfirmasiPembayaran(string $phone, string $namaSantri, string $jenisTagihan, float $nominal): bool
    {
        $nominalFormatted = 'Rp ' . number_format($nominal, 0, ',', '.');
        $message = "Assalamu'alaikum ✅\n\n"
            . "Pembayaran *{$jenisTagihan}* atas nama *{$namaSantri}* sebesar *{$nominalFormatted}* telah *berhasil diverifikasi* oleh petugas.\n\n"
            . "Kwitansi tersedia di aplikasi. Terima kasih 🙏";

        return $this->send($phone, $message);
    }

    /**
     * Template: Hasil seleksi PPDB - Diterima.
     */
    public function sendHasilPpdbDiterima(string $phone, string $namaCalonSantri): bool
    {
        $message = "Assalamu'alaikum 🎉\n\n"
            . "Selamat! Calon santri *{$namaCalonSantri}* dinyatakan *DITERIMA* di Pondok Pesantren Al-Ausath.\n\n"
            . "Silakan login ke aplikasi untuk melihat informasi selanjutnya.\n\n"
            . "Barakallahu fiikum 🙏";

        return $this->send($phone, $message);
    }

    /**
     * Template: Hasil seleksi PPDB - Ditolak.
     */
    public function sendHasilPpdbDitolak(string $phone, string $namaCalonSantri): bool
    {
        $message = "Assalamu'alaikum,\n\n"
            . "Kami informasikan bahwa calon santri *{$namaCalonSantri}* belum bisa kami terima pada pendaftaran kali ini.\n\n"
            . "Terima kasih telah mendaftar ke Pondok Pesantren Al-Ausath. Semoga di lain kesempatan. 🙏";

        return $this->send($phone, $message);
    }

    /**
     * Template: OTP untuk login.
     */
    public function sendOtp(string $phone, string $kodeOtp): bool
    {
        $message = "Kode OTP Pondok Pesantren Al-Ausath Anda adalah: *{$kodeOtp}*\n\n"
            . "Kode ini berlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun.";

        return $this->send($phone, $message);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function sendViaFonnte(string $phone, string $message): bool
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->post('https://api.fonnte.com/send', [
            'target'  => $phone,
            'message' => $message,
            'delay'   => 2,
        ]);

        $body = $response->json();
        $status = (bool) ($body['status'] ?? false);

        if (!$status) {
            Log::warning("[WhatsApp/Fonnte] Gagal mengirim ke {$phone}: " . json_encode($body));
        } else {
            Log::info("[WhatsApp/Fonnte] Pesan terkirim ke {$phone}.");
        }

        return $status;
    }

    /**
     * Normalisasi nomor telepon ke format internasional tanpa tanda +.
     * 08xxxx → 628xxxx
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
