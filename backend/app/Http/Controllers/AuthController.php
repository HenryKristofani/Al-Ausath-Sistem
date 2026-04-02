<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AkunPendaftar;
use App\Models\DataPetugas;
use App\Models\DataAkunSantri;
use App\Models\PpdbPendaftar;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AuthController extends Controller
{
    public function loginPpdb(Request $request)
    {
        if (!$request->filled('username') && $request->filled('no_pendaftaran')) {
            $request->merge(['username' => $request->no_pendaftaran]);
        }

        $request->merge(['role' => 'ppdb']);

        return $this->login($request);
    }

    public function registerPpdb(Request $request)
    {
        $request->merge(['role' => 'ppdb']);

        return $this->register($request);
    }

    public function login(Request $request)
    {
        $request->validate([
            'role' => 'required|in:petugas,santri,ppdb',
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($request->role === 'petugas') {
            $user = DataPetugas::where('alamat_email', $request->username)->first();
            $guard = 'petugas';
        } elseif ($request->role === 'santri') {
            $user = DataAkunSantri::where('nama_akun', $request->username)->first();
            $guard = 'santri';
        } else {
            $pendaftar = PpdbPendaftar::with('akun')
                ->where('no_pendaftaran', $request->username)
                ->orWhere('no_pendaftaran_final', $request->username)
                ->first();

            $user = $pendaftar?->akun;
            $guard = 'ppdb';
        }

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'username' => ['Kredensial yang diberikan tidak sesuai.'],
            ]);
        }

        if (in_array($guard, ['petugas', 'santri'], true) && strtolower((string) $user->status) !== 'aktif') {
            return response()->json([
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.'
            ], 403);
        }

        Auth::guard($guard)->login($user);

        $request->session()->regenerate();

        if (in_array($guard, ['petugas', 'santri'], true)) {
            $user->update(['last_login' => now()]);
        }

        $userPayload = [
            'id' => $user->getKey(),
            'nama_lengkap' => $user->nama_lengkap ?? $user->nama,
            'email' => $user->alamat_email ?? $user->email,
            'nomor_induk' => $user->nomor_induk ?? null,
            'peran_akun' => $user->peran_akun ?? null,
            'pilihan_unit' => $user->pilihan_unit ?? null,
        ];

        if ($guard === 'ppdb') {
            $userPayload['phone'] = $user->phone;
            $userPayload['pendaftaran'] = [
                'id_pendaftaran' => $pendaftar?->id_pendaftaran,
                'no_pendaftaran' => $pendaftar?->no_pendaftaran,
                'no_pendaftaran_final' => $pendaftar?->no_pendaftaran_final,
                'status_verifikasi' => $pendaftar?->status_verifikasi,
            ];
        }

        return response()->json([
            'message' => 'Login Berhasil!',
            'role' => $guard,
            'user' => $userPayload,
        ]);
    }

    public function logout(Request $request)
    {
        if (Auth::guard('petugas')->check()) {
            Auth::guard('petugas')->logout();
        } elseif (Auth::guard('santri')->check()) {
            Auth::guard('santri')->logout();
        } elseif (Auth::guard('ppdb')->check()) {
            Auth::guard('ppdb')->logout();
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
            'role' => 'required|in:petugas,santri,ppdb',
            'nama_lengkap' => 'required_unless:role,ppdb|string|max:255',
            'password' => 'required|string|min:6|confirmed',
            
            // Validasi petugas
            'alamat_email' => 'required_if:role,petugas|email|unique:data_petugas,alamat_email',
            'nomor_induk' => 'required_if:role,petugas|string|unique:data_petugas,nomor_induk',
            'peran_akun' => 'required_if:role,petugas|string|in:Petugas Admin,Petugas Tata Usaha,Petugas PPDB,Staf Pengajar',
            'pilihan_unit' => 'nullable|string',
            
            // Validasi santri
            'nama_akun' => 'required_if:role,santri|string|unique:data_akun_santri,nama_akun',
            'email_santri' => 'nullable|email|unique:data_akun_santri,alamat_email',
            'nomor_induk_santri' => 'nullable|unique:data_akun_santri,nomor_induk',
            'nama_unit' => 'nullable|string',
            'nama_kelas' => 'nullable|string',
            'tahun_ajaran' => 'nullable|string',
            'nomor_telepon' => 'nullable|string',

            // Validasi PPDB
            'no_pendaftaran' => 'required_if:role,ppdb|string|max:50|unique:ppdb_pendaftar,no_pendaftaran',
            'no_pendaftaran_final' => 'nullable|string|max:50',
            'nama_calon' => 'required_if:role,ppdb|string|max:200',
            'jenjang' => 'nullable|string|max:20',
            'nomor_umi' => 'nullable|string|max:50',
            'asal_kota' => 'nullable|string|max:100',
            'is_luar_kota' => 'nullable|boolean',
            'status_verifikasi' => 'nullable|string|max:30',
            'tanggal_daftar' => 'nullable|date',
            'email_ppdb' => 'nullable|email|unique:akun_pendaftar,email',
            'phone_ppdb' => 'nullable|string|max:30',
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
        } elseif ($request->role === 'santri') {
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
        } else {
            $pendaftar = DB::transaction(function () use ($request, $hashedPassword) {
                $akun = AkunPendaftar::create([
                    'nama' => $request->nama_calon,
                    'email' => $request->email_ppdb,
                    'phone' => $request->phone_ppdb,
                    'password_hash' => $hashedPassword,
                ]);

                return PpdbPendaftar::create([
                    'id_akun' => $akun->id_akun,
                    'no_pendaftaran' => $request->no_pendaftaran,
                    'no_pendaftaran_final' => $request->no_pendaftaran_final ?: $request->no_pendaftaran,
                    'nama_calon' => $request->nama_calon,
                    'jenjang' => $request->jenjang,
                    'nomor_umi' => $request->nomor_umi,
                    'asal_kota' => $request->asal_kota,
                    'is_luar_kota' => (bool) $request->is_luar_kota,
                    'status_verifikasi' => $request->status_verifikasi ?: 'pending',
                    'tanggal_daftar' => $request->filled('tanggal_daftar')
                        ? Carbon::parse($request->tanggal_daftar)->toDateString()
                        : now()->toDateString(),
                ]);
            });

            $user = $pendaftar->akun;
            $guard = 'ppdb';
        }


        $userPayload = [
            'id' => $user->getKey(),
            'nama_lengkap' => $user->nama_lengkap ?? $user->nama,
            'email' => $user->alamat_email ?? $user->email,
        ];

        if ($guard === 'ppdb') {
            $userPayload['pendaftaran'] = [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'id_akun' => $pendaftar->id_akun,
                'no_pendaftaran' => $pendaftar->no_pendaftaran,
                'no_pendaftaran_final' => $pendaftar->no_pendaftaran_final,
                'nama_calon' => $pendaftar->nama_calon,
                'jenjang' => $pendaftar->jenjang,
                'nomor_umi' => $pendaftar->nomor_umi,
                'asal_kota' => $pendaftar->asal_kota,
                'is_luar_kota' => $pendaftar->is_luar_kota,
                'status_verifikasi' => $pendaftar->status_verifikasi,
                'tanggal_daftar' => $pendaftar->tanggal_daftar,
            ];
        }

        return response()->json([
            'message' => 'Registrasi berhasil!',
            'role' => $guard,
            'user' => $userPayload,
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
        } elseif (Auth::guard('ppdb')->check()) {
            $guard = 'ppdb';
            $user = Auth::guard('ppdb')->user();
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