<?php

namespace App\Support;

use App\Models\OtpToken;
use Illuminate\Support\Facades\Log;

class OtpService
{
    protected int $ttlMinutes = 5;
    protected int $length = 6;
    protected WhatsAppGatewayService $whatsapp;

    public function __construct(WhatsAppGatewayService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    /**
     * Generate OTP baru dan kirim via WhatsApp.
     * Invalidate OTP lama yang belum digunakan.
     */
    public function generateAndSend(string $identifier, string $guard, string $phoneOrEmail): bool
    {
        // Hapus OTP lama yang belum digunakan
        OtpToken::where('identifier', $identifier)
            ->where('guard', $guard)
            ->where('sudah_digunakan', false)
            ->delete();

        $kode = str_pad((string) random_int(0, 999999), $this->length, '0', STR_PAD_LEFT);

        OtpToken::create([
            'identifier'      => $identifier,
            'guard'           => $guard,
            'kode'            => $kode,
            'sudah_digunakan' => false,
            'kadaluarsa_at'   => now()->addMinutes($this->ttlMinutes),
        ]);

        Log::info("[OTP] Kode OTP dibuat untuk {$identifier} (guard: {$guard}).");

        // Kirim via WhatsApp jika nomor valid (bukan email)
        if (filter_var($phoneOrEmail, FILTER_VALIDATE_EMAIL)) {
            // TODO: kirim via email jika diperlukan
            Log::debug("[OTP] Identifier adalah email, pengiriman email belum dikonfigurasi.");
            return true; // Tetap return true agar flow tidak terputus
        }

        return $this->whatsapp->sendOtp($phoneOrEmail, $kode);
    }

    /**
     * Verifikasi OTP yang dimasukkan user.
     * Return true jika valid, false jika tidak.
     */
    public function verify(string $identifier, string $guard, string $kodeInput): bool
    {
        $otp = OtpToken::where('identifier', $identifier)
            ->where('guard', $guard)
            ->where('kode', $kodeInput)
            ->where('sudah_digunakan', false)
            ->where('kadaluarsa_at', '>=', now())
            ->first();

        if (!$otp) {
            return false;
        }

        $otp->update(['sudah_digunakan' => true]);
        return true;
    }
}
