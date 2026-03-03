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

    /**
     * auth.register
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // 1. Validasi inputan dari Frontend
        $request->validate([
            'role' => 'required|in:petugas,santri',
            'nama_lengkap' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
            
            // Validasi spesifik untuk petugas
            'alamat_email' => 'required_if:role,petugas|email|unique:data_petugas,alamat_email',
            'nomor_induk' => 'required_if:role,petugas|string|unique:data_petugas,nomor_induk',
            'peran_akun' => 'required_if:role,petugas|string',
            'pilihan_unit' => 'nullable|string',
            'nomor_telepon' => 'nullable|string',
            
            // Validasi spesifik untuk santri
            'nama_akun' => 'required_if:role,santri|string|unique:data_akun_santri,nama_akun',
            'nama_unit' => 'nullable|string',
            'nama_kelas' => 'nullable|string',
            'tahun_ajaran' => 'nullable|string',
        ]);

        // 2. Hash password
        $hashedPassword = Hash::make($request->password);

        // 3. Buat akun sesuai role
        if ($request->role === 'petugas') {
            $user = DataPetugas::create([
                'nomor_induk' => $request->nomor_induk,
                'nama_lengkap' => $request->nama_lengkap,
                'peran_akun' => $request->peran_akun,
                'pilihan_unit' => $request->pilihan_unit,
                'alamat_email' => $request->alamat_email,
                'nomor_telepon' => $request->nomor_telepon,
                'password_hash' => $hashedPassword,
                'status' => 'aktif',
            ]);
            
            $guard = 'petugas';
        } else {
            // Untuk santri
            $user = DataAkunSantri::create([
                'nomor_induk' => $request->nomor_induk ?? null,
                'nama_akun' => $request->nama_akun,
                'nama_lengkap' => $request->nama_lengkap,
                'nama_unit' => $request->nama_unit,
                'nama_kelas' => $request->nama_kelas,
                'tahun_ajaran' => $request->tahun_ajaran,
                'alamat_email' => $request->alamat_email ?? null,
                'nomor_telepon' => $request->nomor_telepon,
                'password_hash' => $hashedPassword,
                'status' => 'aktif',
            ]);
            
            $guard = 'santri';
        }

        // 4. Auto-login setelah registrasi
        Auth::guard($guard)->login($user);

        // 5. Response sukses
        return response()->json([
            'message' => 'Registrasi berhasil!',
            'role' => $guard,
            'user' => [
                'id' => $user->getKey(),
                'nama_lengkap' => $user->nama_lengkap,
                'role' => $guard,
            ]
        ], 201);
    }
}