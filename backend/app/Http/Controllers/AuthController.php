<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AkunPendaftar;
use App\Models\DataPetugas;
use App\Models\DataAkunSantri;
use App\Models\PpdbNotifikasi;
use App\Models\PpdbPendaftar;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Carbon;
use App\Support\PpdbRegistrationNumberService;

class AuthController extends Controller
{
    public function loginPpdb(Request $request)
    {
        if (!$request->filled('identifier') && $request->filled('email')) {
            $request->merge(['identifier' => $request->email]);
        }

        if (!$request->filled('identifier') && $request->filled('username')) {
            $request->merge(['identifier' => $request->username]);
        }

        $validated = $request->validate([
            'identifier' => 'required|string|max:150',
            'password' => 'required|string',
        ]);

        $identifier = trim((string) $validated['identifier']);
        $identifierIsNumeric = ctype_digit($identifier);
        $identifierInt = $identifierIsNumeric ? (int) $identifier : null;

        $user = AkunPendaftar::with(['pendaftaran' => fn ($q) => $q->orderByDesc('id_pendaftaran')])
            ->where(function ($query) use ($identifier, $identifierIsNumeric, $identifierInt) {
                $query->where('email', $identifier)
                    ->orWhere('id_akun', $identifierIsNumeric ? $identifierInt : -1)
                    ->orWhereHas('pendaftaran', function ($pendaftaranQuery) use ($identifier) {
                        $pendaftaranQuery
                            ->where('no_pendaftaran', $identifier)
                            ->orWhere('no_pendaftaran_final', $identifier);
                    });

                if ($identifierIsNumeric && $identifierInt) {
                    $query->orWhereHas('pendaftaran', function ($pendaftaranQuery) use ($identifierInt) {
                        $pendaftaranQuery->where('id_pendaftaran', $identifierInt);
                    });
                }
            })
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'identifier' => ['Kredensial yang diberikan tidak sesuai.'],
            ]);
        }

        Auth::guard('ppdb')->login($user);
        $request->session()->regenerate();

        $pendaftarAktif = $user->pendaftaran()
            ->orderByDesc('id_pendaftaran')
            ->first();

        if (!$pendaftarAktif) {
            $pendaftarAktif = $this->firstOrCreateDraftPendaftaran($user);
        }

        $pendaftarAktif->load(['tes', 'verifikasi']);

        // Gunakan token API agar alur mobile / client non-cookie tetap stabil.
        $user->tokens()->delete();
        $accessToken = $user->createToken('ppdb-api')->plainTextToken;

        $daftarPendaftaran = $user->pendaftaran()
            ->orderByDesc('id_pendaftaran')
            ->get();

        $userPayload = [
            'id' => $user->getKey(),
            'nama_lengkap' => $user->nama,
            'email' => $user->email,
            'phone' => $user->phone,
            'pendaftaran_aktif' => $pendaftarAktif,
            'daftar_pendaftaran' => $daftarPendaftaran
                ->map(fn ($item) => [
                    'id_pendaftaran' => $item->id_pendaftaran,
                    'nama_calon' => $item->nama_calon,
                    'jenjang' => $item->jenjang,
                    'no_pendaftaran' => $item->no_pendaftaran,
                    'no_pendaftaran_final' => $item->no_pendaftaran_final,
                    'nomor_induk_generated' => $item->nomor_induk_generated,
                    'status_verifikasi' => $item->status_verifikasi,
                ])
                ->values(),
            'flow' => $this->buildPpdbFlowState($pendaftarAktif),
        ];

        return response()->json([
            'message' => 'Login Berhasil!',
            'role' => 'ppdb',
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'user' => $userPayload,
        ]);
    }

    public function registerPpdb(Request $request)
    {
        if (!$request->filled('email_ppdb') && $request->filled('email')) {
            $request->merge(['email_ppdb' => $request->email]);
        }

        if (!$request->filled('phone_ppdb') && $request->filled('phone')) {
            $request->merge(['phone_ppdb' => $request->phone]);
        }

        $validated = $request->validate([
            'nama' => 'nullable|string|max:200',
            'email_ppdb' => 'required|email|max:150|unique:akun_pendaftar,email',
            'phone_ppdb' => 'required|string|max:30',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $hashedPassword = Hash::make($validated['password']);

        $namaAkun = $validated['nama']
            ?? ($validated['email_ppdb'] ? explode('@', $validated['email_ppdb'])[0] : 'Pendaftar');

        $user = AkunPendaftar::create([
            'nama' => $namaAkun,
            'email' => $validated['email_ppdb'],
            'phone' => $validated['phone_ppdb'],
            'password_hash' => $hashedPassword,
        ]);

        $pendaftar = $this->firstOrCreateDraftPendaftaran($user);

        return response()->json([
            'message' => 'Registrasi akun pendaftar berhasil. ID pendaftaran sudah dibuat.',
            'role' => 'ppdb',
            'user' => [
                'id' => $user->getKey(),
                'id_akun' => $user->id_akun,
                'nama_lengkap' => $user->nama,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'pendaftaran' => [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'no_pendaftaran' => $pendaftar->no_pendaftaran,
                'no_pendaftaran_final' => $pendaftar->no_pendaftaran_final,
                'status_verifikasi' => $pendaftar->status_verifikasi,
                'tanggal_daftar' => $pendaftar->tanggal_daftar,
            ],
            'next_step' => [
                'endpoint' => '/api/ppdb/login',
                'method' => 'POST',
            ],
        ], 201);
    }

    public function createIdentitasPendaftaranPpdb(Request $request)
    {
        $validated = $request->validate([
            'id_akun' => 'required_without:email_ppdb|nullable|integer|exists:akun_pendaftar,id_akun',
            'email_ppdb' => 'required_without:id_akun|nullable|email|max:150|exists:akun_pendaftar,email',
        ]);

        if (!empty($validated['id_akun'])) {
            $akun = AkunPendaftar::findOrFail($validated['id_akun']);
        } else {
            $akun = AkunPendaftar::where('email', $validated['email_ppdb'])->firstOrFail();
        }

        if (!empty($validated['email_ppdb']) && strcasecmp((string) $akun->email, (string) $validated['email_ppdb']) !== 0) {
            return response()->json([
                'message' => 'Email tidak sesuai dengan akun pendaftar.',
            ], 422);
        }

        $pendaftar = $this->firstOrCreateDraftPendaftaran($akun);

        return response()->json([
            'message' => 'ID pendaftaran dan nomor pendaftar berhasil dibuat.',
            'data' => [
                'id_akun' => $akun->id_akun,
                'email_ppdb' => $akun->email,
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'nomor_pendaftar' => $pendaftar->no_pendaftaran,
                'no_pendaftaran' => $pendaftar->no_pendaftaran,
                'no_pendaftaran_final' => $pendaftar->no_pendaftaran_final,
                'status_verifikasi' => $pendaftar->status_verifikasi,
                'tanggal_daftar' => $pendaftar->tanggal_daftar,
            ],
            'next_step' => [
                'endpoint' => '/api/ppdb/login',
                'method' => 'POST',
            ],
        ]);
    }

    public function previewNoPendaftaran(Request $request)
    {
        $tanggalDaftar = $request->filled('tanggal_daftar')
            ? Carbon::parse($request->tanggal_daftar)
            : now();

        $nomorService = $this->registrationNumberService();
        $idPendaftaranPrediksi = $nomorService->generatePendaftaranId($tanggalDaftar);
        $noPendaftaran = $nomorService->generateInitialNumber($tanggalDaftar);
        $noPendaftaranFinal = $nomorService->generateFinalNumber($noPendaftaran);
        $nomorIndukPrediksi = $nomorService->generateNomorIndukFromPendaftaran(new PpdbPendaftar([
            'no_pendaftaran' => $noPendaftaran,
            'no_pendaftaran_final' => $noPendaftaranFinal,
            'tanggal_daftar' => $tanggalDaftar->toDateString(),
        ]));

        return response()->json([
            'message' => 'Preview nomor pendaftaran berhasil dibuat.',
            'data' => [
                'id_pendaftaran_prediksi' => $idPendaftaranPrediksi,
                'no_pendaftaran' => $noPendaftaran,
            ],
        ]);
    }

    public function rekapPengumumanPpdb(Request $request)
    {
        $acceptedStatuses = ['diterima', 'lulus', 'accepted'];

        $dataDiterima = PpdbPendaftar::query()
            ->whereIn('status_verifikasi', $acceptedStatuses)
            ->orderBy('nama_calon')
            ->get([
                'id_pendaftaran',
                'no_pendaftaran',
                'no_pendaftaran_final',
                'nama_calon',
                'program_pendaftaran',
                'jenjang',
                'status_verifikasi',
                'tanggal_pengumuman',
            ]);

        $dokumenPengumuman = PpdbNotifikasi::query()
            ->whereIn('type', ['pengumuman_file', 'dokumen_pengumuman'])
            ->orderByDesc('id_notif')
            ->get([
                'id_notif',
                'type',
                'konten',
                'sent_at',
                'status_kirim',
            ])
            ->map(fn ($row) => [
                'id_notif' => $row->id_notif,
                'jenis' => $row->type,
                'url_atau_path' => $row->konten,
                'tanggal' => $row->sent_at,
                'status' => $row->status_kirim,
            ])
            ->values();

        return response()->json([
            'message' => 'Rekap pengumuman PPDB berhasil dimuat.',
            'data' => [
                'status_diterima' => $acceptedStatuses,
                'jumlah_diterima' => $dataDiterima->count(),
                'daftar_diterima' => $dataDiterima,
                'dokumen_pengumuman' => $dokumenPengumuman,
            ],
        ]);
    }

    public function dashboardPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $pendaftar = $akun->pendaftaran()
            ->orderByDesc('id_pendaftaran')
            ->first();

        if (!$pendaftar) {
            return response()->json([
                'message' => 'ID pendaftaran belum dibuat untuk akun ini.',
                'next_step' => [
                    'endpoint' => '/api/ppdb/pendaftaran/create-identitas',
                    'method' => 'POST',
                    'payload_hint' => [
                        'id_akun' => $akun->id_akun,
                        'email_ppdb' => $akun->email,
                    ],
                ],
            ], 422);
        }

        $pendaftar->load(['tes', 'verifikasi']);

        return response()->json([
            'message' => 'Dashboard pendaftar berhasil dimuat.',
            'data' => [
                'akun' => [
                    'id_akun' => $akun->id_akun,
                    'nama' => $akun->nama,
                    'email' => $akun->email,
                    'phone' => $akun->phone,
                ],
                'pendaftaran' => [
                    'id_pendaftaran' => $pendaftar->id_pendaftaran,
                    'no_pendaftaran' => $pendaftar->no_pendaftaran,
                    'no_pendaftaran_final' => $pendaftar->no_pendaftaran_final,
                    'waktu_pendaftaran' => $pendaftar->waktu_pendaftaran ?: $pendaftar->created_at,
                    'nama_calon' => $pendaftar->nama_calon,
                    'program_pendaftaran' => $pendaftar->program_pendaftaran,
                    'jenjang' => $pendaftar->jenjang,
                    'jenis_kelamin' => $pendaftar->jenis_kelamin,
                    'tempat_lahir' => $pendaftar->tempat_lahir,
                    'tanggal_lahir' => $pendaftar->tanggal_lahir,
                    'nik_calon_santri' => $pendaftar->nik_calon_santri,
                    'alamat_lengkap' => $pendaftar->alamat_lengkap,
                    'riwayat_penyakit' => $pendaftar->riwayat_penyakit,
                    'nama_ayah' => $pendaftar->nama_ayah,
                    'penghasilan_ayah' => $pendaftar->penghasilan_ayah,
                    'no_hp_calon' => $pendaftar->no_hp_calon,
                    'nama_ibu' => $pendaftar->nama_ibu,
                    'no_hp_ibu' => $pendaftar->no_hp_ibu,
                    'soal_jawab' => $pendaftar->soal_jawab,
                    'file_akta_path' => $pendaftar->file_akta_path,
                    'file_kk_path' => $pendaftar->file_kk_path,
                    'file_surat_rekomendasi_path' => $pendaftar->file_surat_rekomendasi_path,
                    'surat_pernyataan_setuju' => (bool) $pendaftar->surat_pernyataan_setuju,
                    'surat_pernyataan_file_path' => $pendaftar->surat_pernyataan_file_path,
                    'nomor_umi' => $pendaftar->nomor_umi,
                    'asal_kota' => $pendaftar->asal_kota,
                    'is_luar_kota' => (bool) $pendaftar->is_luar_kota,
                    'status_verifikasi' => $pendaftar->status_verifikasi,
                    'tanggal_daftar' => $pendaftar->tanggal_daftar,
                    'tanggal_pengumuman' => $this->resolveTanggalPengumuman($pendaftar),
                ],
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ],
        ]);
    }

    public function updateFormPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $idPendaftaran = $request->input('id_pendaftaran');
        $pendaftar = $idPendaftaran
            ? PpdbPendaftar::where('id_pendaftaran', $idPendaftaran)
                ->where('id_akun', $akun->id_akun)
                ->first()
            : $akun->pendaftaran()->orderByDesc('id_pendaftaran')->first();

        if (!$pendaftar) {
            return response()->json([
                'message' => 'Data pendaftaran tidak ditemukan untuk akun ini.'
            ], 404);
        }

        $validated = $request->validate([
            'nama_calon' => 'required|string|max:200',
            'program_pendaftaran' => 'required|string|max:100',
            'jenjang' => 'nullable|string|max:20',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir' => 'required|string|max:100',
            'tanggal_lahir' => 'required|date',
            'nik_calon_santri' => 'required|string|max:30',
            'alamat_lengkap' => 'required|string',
            'riwayat_penyakit' => 'nullable|string',
            'nama_ayah' => 'required|string|max:200',
            'penghasilan_ayah' => 'nullable|string|max:100',
            'no_hp_calon' => 'required|string|max:30',
            'nama_ibu' => 'required|string|max:200',
            'no_hp_ibu' => 'required|string|max:30',
            'soal_jawab' => 'nullable|string',
            'file_akta_path' => 'required|string',
            'file_kk_path' => 'required|string',
            'file_surat_rekomendasi_path' => 'required|string',
            'surat_pernyataan_setuju' => 'required|boolean',
            'surat_pernyataan_file_path' => 'nullable|string',
            'nomor_umi' => 'nullable|string|max:50',
            'asal_kota' => 'nullable|string|max:100',
            'phone_ppdb' => 'nullable|string|max:30',
        ]);

        $jenjangDipakai = mb_strtolower((string) ($validated['jenjang'] ?? $pendaftar->jenjang));
        if ($jenjangDipakai === 'smp' && empty($validated['nomor_umi'])) {
            throw ValidationException::withMessages([
                'nomor_umi' => ['Nomor UMI wajib diisi untuk jenjang SMP.'],
            ]);
        }

        $asalKota = $validated['asal_kota'] ?? $pendaftar->asal_kota;

        $pendaftar->fill([
            'nama_calon' => $validated['nama_calon'],
            'program_pendaftaran' => $validated['program_pendaftaran'],
            'jenjang' => $validated['jenjang'] ?? $pendaftar->jenjang,
            'jenis_kelamin' => $validated['jenis_kelamin'],
            'tempat_lahir' => $validated['tempat_lahir'],
            'tanggal_lahir' => $validated['tanggal_lahir'],
            'nik_calon_santri' => $validated['nik_calon_santri'],
            'alamat_lengkap' => $validated['alamat_lengkap'],
            'riwayat_penyakit' => $validated['riwayat_penyakit'] ?? null,
            'nama_ayah' => $validated['nama_ayah'],
            'penghasilan_ayah' => $validated['penghasilan_ayah'] ?? null,
            'no_hp_calon' => $validated['no_hp_calon'],
            'nama_ibu' => $validated['nama_ibu'],
            'no_hp_ibu' => $validated['no_hp_ibu'],
            'soal_jawab' => $validated['soal_jawab'] ?? null,
            'file_akta_path' => $validated['file_akta_path'],
            'file_kk_path' => $validated['file_kk_path'],
            'file_surat_rekomendasi_path' => $validated['file_surat_rekomendasi_path'],
            'surat_pernyataan_setuju' => $validated['surat_pernyataan_setuju'],
            'surat_pernyataan_file_path' => $validated['surat_pernyataan_file_path'] ?? null,
            'nomor_umi' => $validated['nomor_umi'] ?? null,
            'asal_kota' => $asalKota,
            'is_luar_kota' => $this->registrationNumberService()->isLuarKota($asalKota),
        ])->save();

        if (!empty($validated['phone_ppdb'])) {
            $akun->update(['phone' => $validated['phone_ppdb']]);
        }

        $pendaftar->load(['tes', 'verifikasi']);

        return response()->json([
            'message' => 'Form pendaftaran berhasil disimpan.',
            'data' => [
                'pendaftaran' => $pendaftar,
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ],
        ]);
    }

    public function cekPengumumanPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'id_pendaftaran' => 'required|integer|exists:ppdb_pendaftar,id_pendaftaran',
        ]);

        $pendaftar = PpdbPendaftar::with(['tes', 'verifikasi'])
            ->where('id_pendaftaran', $validated['id_pendaftaran'])
            ->where('id_akun', $akun->id_akun)
            ->first();

        if (!$pendaftar) {
            return response()->json([
                'message' => 'ID pendaftaran tidak ditemukan untuk akun ini.'
            ], 404);
        }

        $flow = $this->buildPpdbFlowState($pendaftar);

        return response()->json([
            'message' => 'Status pengumuman berhasil dicek.',
            'data' => [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'no_pendaftaran' => $pendaftar->no_pendaftaran,
                'status_verifikasi' => $pendaftar->status_verifikasi,
                'tanggal_pengumuman' => $flow['tanggal_pengumuman'],
                'is_pengumuman_dibuka' => $flow['is_pengumuman_dibuka'],
                'flow' => $flow,
            ],
        ]);
    }

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
            $user = DataAkunSantri::where('nama_akun', $request->username)
                ->orWhere('nomor_induk', $request->username)
                ->first();
            $guard = 'santri';
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

        return response()->json([
            'message' => 'Login Berhasil!',
            'role' => $guard,
            'user' => $userPayload,
        ]);
    }

    public function logout(Request $request)
    {
        $apiUser = $request->user();

        if ($apiUser && method_exists($apiUser, 'currentAccessToken')) {
            $currentAccessToken = $apiUser->currentAccessToken();
            if ($currentAccessToken) {
                $currentAccessToken->delete();
            }
        }

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
            'role' => 'required|in:petugas,santri',
            'nama_lengkap' => 'required|string|max:255',
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
        }

        $userPayload = [
            'id' => $user->getKey(),
            'nama_lengkap' => $user->nama_lengkap ?? $user->nama,
            'email' => $user->alamat_email ?? $user->email,
        ];

        return response()->json([
            'message' => 'Registrasi berhasil!',
            'role' => $guard,
            'user' => $userPayload,
        ], 201);
    }

    protected function resolveAuthenticatedPpdbUser(): ?AkunPendaftar
    {
        $guardUser = Auth::guard('ppdb')->user();

        if ($guardUser instanceof AkunPendaftar) {
            return $guardUser;
        }

        $user = Auth::user();
        if ($user instanceof AkunPendaftar) {
            return $user;
        }

        return null;
    }

    protected function firstOrCreateDraftPendaftaran(AkunPendaftar $akun): PpdbPendaftar
    {
        $existing = $akun->pendaftaran()
            ->orderByDesc('id_pendaftaran')
            ->first();

        if ($existing) {
            return $existing;
        }

        $tanggalDaftar = now();
        $nomorService = $this->registrationNumberService();
        $idPendaftaran = $nomorService->generatePendaftaranId($tanggalDaftar);
        $noPendaftaran = $nomorService->generateInitialNumber($tanggalDaftar);
        $noPendaftaranFinal = $nomorService->generateFinalNumber($noPendaftaran);

        $namaDraft = trim((string) ($akun->nama ?? ''));
        if ($namaDraft === '') {
            $email = (string) ($akun->email ?? '');
            $namaDraft = $email !== '' ? explode('@', $email)[0] : 'Calon Peserta';
        }

        $pendaftar = new PpdbPendaftar([
            'id_akun' => $akun->id_akun,
            'no_pendaftaran' => $noPendaftaran,
            'no_pendaftaran_final' => $noPendaftaranFinal,
            'nama_calon' => $namaDraft,
            'status_verifikasi' => 'pending',
            'tanggal_daftar' => $tanggalDaftar->toDateString(),
            'waktu_pendaftaran' => $tanggalDaftar,
            'is_luar_kota' => false,
        ]);

        $pendaftar->id_pendaftaran = $idPendaftaran;
        $pendaftar->save();

        return $pendaftar;
    }

    protected function buildPpdbFlowState(PpdbPendaftar $pendaftar): array
    {
        $tes = $pendaftar->relationLoaded('tes') ? $pendaftar->tes : $pendaftar->tes()->first();
        $verifikasi = $pendaftar->relationLoaded('verifikasi') ? $pendaftar->verifikasi : $pendaftar->verifikasi()->first();

        $statusTes = mb_strtolower((string) ($tes?->status_tes ?? ''));
        $adaTes = $tes !== null && (
            trim((string) ($tes->soal_tes ?? '')) !== '' || trim((string) ($tes->status_tes ?? '')) !== ''
        );
        $tesSelesai = in_array($statusTes, ['selesai', 'sudah', 'done', 'submitted'], true);

        $tanggalPengumuman = $this->resolveTanggalPengumuman($pendaftar, $verifikasi?->tanggal_verif ?? null);
        $statusVerifikasi = mb_strtolower((string) ($pendaftar->status_verifikasi ?? 'pending'));

        $isPengumumanDibuka = false;
        if ($tanggalPengumuman) {
            $isPengumumanDibuka = now()->startOfDay()->greaterThanOrEqualTo(
                Carbon::parse($tanggalPengumuman)->startOfDay()
            );
        } elseif (in_array($statusVerifikasi, ['diterima', 'ditolak'], true)) {
            $isPengumumanDibuka = true;
        }

        $showTesPage = $adaTes && !$tesSelesai;
        $showTanggalPengumuman = !$showTesPage;
        $showFormPengumuman = !$showTesPage && $isPengumumanDibuka;

        return [
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'show_form_pendaftaran' => true,
            'show_halaman_tes' => $showTesPage,
            'show_halaman_tanggal_pengumuman' => $showTanggalPengumuman,
            'show_form_pengumuman' => $showFormPengumuman,
            'tanggal_pengumuman' => $tanggalPengumuman,
            'is_pengumuman_dibuka' => $isPengumumanDibuka,
            'status_verifikasi' => $pendaftar->status_verifikasi,
        ];
    }

    protected function resolveTanggalPengumuman(PpdbPendaftar $pendaftar, $tanggalVerifikasi = null): ?string
    {
        if (!empty($pendaftar->tanggal_pengumuman)) {
            return Carbon::parse($pendaftar->tanggal_pengumuman)->toDateString();
        }

        if (!empty($tanggalVerifikasi)) {
            return Carbon::parse($tanggalVerifikasi)->toDateString();
        }

        return null;
    }

    protected function registrationNumberService(): PpdbRegistrationNumberService
    {
        return app(PpdbRegistrationNumberService::class);
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

        if ($guard === 'ppdb' && $user instanceof AkunPendaftar) {
            $pendaftar = $user->pendaftaran()->orderByDesc('id_pendaftaran')->first();

            if (!$pendaftar) {
                return response()->json([
                    'user' => $user,
                    'role' => $guard,
                    'message' => 'ID pendaftaran belum dibuat.',
                    'next_step' => [
                        'endpoint' => '/api/ppdb/pendaftaran/create-identitas',
                        'method' => 'POST',
                        'payload_hint' => [
                            'id_akun' => $user->id_akun,
                            'email_ppdb' => $user->email,
                        ],
                    ],
                ], 422);
            }

            $pendaftar->load(['tes', 'verifikasi']);

            return response()->json([
                'user' => $user,
                'role' => $guard,
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'pendaftaran_aktif' => $pendaftar,
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ]);
        }

        return response()->json([
            'user' => $user,
            'role' => $guard
        ]);
    }
}