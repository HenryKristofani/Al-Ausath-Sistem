<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DataPetugas;
use App\Models\DataAkunSantri;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'role' => 'required|in:petugas,santri',
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($request->role === 'petugas') {
            $user = DataPetugas::where('alamat_email', $request->username)->first();
            $guard = 'petugas';
        } else {
            $user = DataAkunSantri::where('nama_akun', $request->username)->first();
            $guard = 'santri';
        }

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'username' => ['Kredensial yang diberikan tidak sesuai.'],
            ]);
        }

        if (strtolower($user->status) !== 'aktif') {
            return response()->json([
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.'
            ], 403);
        }

        Auth::guard($guard)->login($user);

        $request->session()->regenerate();

        $user->update(['last_login' => now()]);

        return response()->json([
            'message' => 'Login Berhasil!',
            'role' => $guard,
            'user' => [
                'id'          => $user->getKey(),
                'nama_lengkap'=> $user->nama_lengkap,
                'email'       => $user->alamat_email,
                'nomor_induk' => $user->nomor_induk ?? null,
                'peran_akun'  => $user->peran_akun ?? null,   
                'pilihan_unit'=> $user->pilihan_unit ?? null, 
            ]
        ]);
    }

    public function logout(Request $request)
    {
        if (Auth::guard('petugas')->check()) {
            Auth::guard('petugas')->logout();
        } elseif (Auth::guard('santri')->check()) {
            Auth::guard('santri')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout Berhasil!'])
            ->withCookie(Cookie::forget(config('session.cookie')))
            ->withCookie(Cookie::forget('XSRF-TOKEN'));
    }
    
    public function register(Request $request)
    {
        $request->validate([
            'role' => 'required|in:petugas,santri',
            'nama_lengkap' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            
            // Validasi petugas
            'alamat_email' => 'required_if:role,petugas|email|unique:data_petugas,alamat_email',
            'nomor_induk' => 'required_if:role,petugas|string|unique:data_petugas,nomor_induk',
            'peran_akun' => 'required_if:role,petugas|string',
            'pilihan_unit' => 'nullable|string',
            
            // Validasi santri
            'nama_akun' => 'required_if:role,santri|string|unique:data_akun_santri,nama_akun',
            'email_santri' => 'nullable|email|unique:data_akun_santri,alamat_email',
            'nomor_induk_santri' => 'nullable|unique:data_akun_santri,nomor_induk',
            'nama_unit' => 'nullable|string',
            'nama_kelas' => 'nullable|string',
            'tahun_ajaran' => 'nullable|string',
            'nomor_telepon' => 'nullable|string',
        ]);

        $hashedPassword = Hash::make($request->password);

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
            $user = DataAkunSantri::create([
                'nomor_induk' => $request->nomor_induk_santri,
                'nama_akun' => $request->nama_akun,
                'nama_lengkap' => $request->nama_lengkap,
                'nama_unit' => $request->nama_unit,
                'nama_kelas' => $request->nama_kelas,
                'tahun_ajaran' => $request->tahun_ajaran,
                'alamat_email' => $request->email_santri,
                'nomor_telepon' => $request->nomor_telepon,
                'password_hash' => $hashedPassword,
                'status' => 'aktif',
            ]);
            $guard = 'santri';
        }


        return response()->json([
            'message' => 'Registrasi berhasil!',
            'role' => $guard,
            'user' => [
                'id' => $user->getKey(),
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->alamat_email,
            ]
        ], 201);
    }

    public function me(Request $request)
    {
        $guard = null;
        $user = null;

        if (Auth::guard('petugas')->check()) {
            $guard = 'petugas';
            $user = Auth::guard('petugas')->user();
        } elseif (Auth::guard('santri')->check()) {
            $guard = 'santri';
            $user = Auth::guard('santri')->user();
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'user' => $user,
            'role' => $guard
        ]);
    }
}