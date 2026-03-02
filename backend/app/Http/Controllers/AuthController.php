<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataPetugas;
use App\Models\DataAkunSantri;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validasi inputan dari Frontend (React)
        // Kita pakai nama 'username' dari React, nanti di backend dicocokkan ke email atau nama_akun
        $request->validate([
            'role' => 'required|in:petugas,santri', // Wajib milih role dulu
            'username' => 'required', // Bisa berisi Email (Petugas) atau Nomor Pendaftaran (Santri)
            'password' => 'required',
        ]);

        // 2. Cek Role dan cari ke tabel yang benar
        if ($request->role === 'petugas') {
            // Kalau Petugas, cari berdasarkan alamat_email
            $user = DataPetugas::where('alamat_email', $request->username)->first();
            $guard = 'petugas';
        } else {
            // Kalau Santri, cari berdasarkan nama_akun (nomor pendaftaran)
            $user = DataAkunSantri::where('nama_akun', $request->username)->first();
            $guard = 'santri';
        }

        // 3. Cek apakah user ketemu DAN password_hash-nya cocok
        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'message' => 'Login gagal! Akun tidak ditemukan atau password salah.'
            ], 401); // 401 = Unauthorized
        }

        // 4. Jika sukses, login-kan ke sistem Sanctum menggunakan guard yang sesuai
        Auth::guard($guard)->login($user);

        // 5. Beri jawaban sukses ke React
        return response()->json([
            'message' => 'Login Berhasil!',
            'role' => $guard,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        // Cek guard mana yang sedang aktif, lalu logout-kan
        if (Auth::guard('petugas')->check()) {
            Auth::guard('petugas')->logout();
        } elseif (Auth::guard('santri')->check()) {
            Auth::guard('santri')->logout();
        }

        // Hancurkan sesi dan cookie-nya
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout Berhasil!']);
    }
}