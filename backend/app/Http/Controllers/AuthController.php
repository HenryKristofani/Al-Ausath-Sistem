<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AkunPendaftar;
use App\Models\DataPetugas;
use App\Models\DataAkunSantri;
use App\Models\PpdbNotifikasi;
use App\Models\PpdbBerkas;
use App\Models\PpdbPendaftar;
use App\Models\PpdbTesKonfigurasi;
use App\Models\PembayaranSpp;
use App\Models\PpdbPeriod;
use App\Models\SppSetting;
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
        $activePeriod = PpdbPeriod::sedangBerlangsung()->first();
        if (!$activePeriod) {
            return response()->json([
                'message' => 'Pendaftaran PPDB saat ini sedang ditutup atau belum dibuka.',
            ], 422);
        }

        if ($activePeriod->isKuotaPenuh()) {
            return response()->json([
                'message' => 'Kuota pendaftaran untuk gelombang saat ini sudah penuh.',
            ], 422);
        }

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

        $idPendaftaran = $request->input('id_pendaftaran') ?? $request->query('id_pendaftaran');
        $pendaftar = $idPendaftaran
            ? $akun->pendaftaran()->where('id_pendaftaran', $idPendaftaran)->first()
            : $akun->pendaftaran()->orderByDesc('id_pendaftaran')->first();

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

        // Load period dulu agar ensurePpdbAdministrasiTagihan bisa pakai relasi yang sudah ter-load
        $pendaftar->load(['tes', 'verifikasi', 'period']);
        $this->ensurePpdbAdministrasiTagihan($pendaftar);

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
                    'soal_jawab' => (json_decode($pendaftar->soal_jawab) !== null) ? json_decode($pendaftar->soal_jawab, true) : $pendaftar->soal_jawab,
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
                    'nama_gelombang' => $pendaftar->period?->nama_gelombang ?: 'Gelombang Umum',
                    'tahun_ajaran' => $pendaftar->period?->tahun_ajaran ?: '2026/2027',
                    // POIN 4: Expose URL bukti ortu/guru yang sudah tersimpan
                    // agar dashboard santri bisa menampilkan preview
                    'bukti_ortu_guru_url' => $pendaftar->bukti_ortu_guru_path
                        ? asset('storage/' . $pendaftar->bukti_ortu_guru_path)
                        : null,
                    'bukti_ortu_guru_verified' => (bool) $pendaftar->bukti_ortu_guru_verified,
                    'is_anak_guru' => (bool) $pendaftar->is_anak_guru,
                ],
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ],
        ]);
    }

    public function tesStatusPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $idPendaftaran = $request->input('id_pendaftaran') ?? $request->query('id_pendaftaran');
        $pendaftar = $idPendaftaran
            ? $akun->pendaftaran()->where('id_pendaftaran', $idPendaftaran)->first()
            : $akun->pendaftaran()->orderByDesc('id_pendaftaran')->first();

        if (!$pendaftar) {
            return response()->json([
                'message' => 'ID pendaftaran belum dibuat untuk akun ini.',
            ], 422);
        }

        $pendaftar->load(['tes', 'verifikasi']);
        $flow = $this->buildPpdbFlowState($pendaftar);

        $canAccessTes = (bool) ($flow['show_halaman_tes'] ?? false);
        $step = (string) ($flow['step'] ?? 'lengkapi-form');

        $message = 'Status tes berhasil dimuat.';
        if (!$canAccessTes) {
            if ($step === 'lengkapi-form') {
                $message = 'Lengkapi biodata terlebih dahulu untuk membuka tes.';
            } elseif (!(bool) ($flow['fitur_soal_aktif'] ?? false)) {
                $message = 'Tes belum diaktifkan admin untuk jenjang ini.';
            } elseif (!(bool) ($flow['tes_available'] ?? false)) {
                $message = 'Soal tes untuk jenjang ini belum tersedia.';
            } elseif ((bool) ($flow['tes_submitted'] ?? false)) {
                $message = 'Jawaban tes sudah dikirim.';
            } else {
                $message = 'Tes belum bisa diakses saat ini.';
            }
        }

        return response()->json([
            'message' => $message,
            'data' => [
                'can_access_tes' => $canAccessTes,
                'show_halaman_tes' => (bool) ($flow['show_halaman_tes'] ?? false),
                'pendaftaran_selesai' => (bool) ($flow['pendaftaran_selesai'] ?? false),
                'fitur_soal_aktif' => (bool) ($flow['fitur_soal_aktif'] ?? false),
                'soal_tes' => (string) ($flow['soal_tes'] ?? ''),
                'form_schema' => is_array($flow['form_schema'] ?? null) ? $flow['form_schema'] : [],
                'tes_required' => (bool) ($flow['tes_required'] ?? false),
                'tes_available' => (bool) ($flow['tes_available'] ?? false),
                'tes_finished' => (bool) ($flow['tes_finished'] ?? false),
                'tes_submitted' => (bool) ($flow['tes_submitted'] ?? false),
                'tes_title' => 'Tes Seleksi PPDB',
                'tes_description' => $canAccessTes
                    ? 'Kerjakan tes sesuai instruksi admin untuk jenjang pendaftaran Anda.'
                    : 'Tes akan muncul setelah biodata lengkap dan konfigurasi tes diaktifkan oleh admin.',
                'step' => $step,
                'message' => $message,
            ],
        ]);
    }

    public function pembayaranStatusPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $idPendaftaran = $request->input('id_pendaftaran') ?? $request->query('id_pendaftaran');
        $pendaftar = $idPendaftaran
            ? $akun->pendaftaran()->where('id_pendaftaran', $idPendaftaran)->first()
            : $akun->pendaftaran()->orderByDesc('id_pendaftaran')->first();

        if (!$pendaftar) {
            return response()->json([
                'message' => 'ID pendaftaran belum dibuat untuk akun ini.',
            ], 422);
        }

        $this->ensurePpdbAdministrasiTagihan($pendaftar);
        $flow = $this->buildPpdbFlowState($pendaftar);

        return response()->json([
            'message' => 'Status pembayaran PPDB berhasil dimuat.',
            'data' => [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'show_halaman_pembayaran_ppdb' => (bool) ($flow['show_halaman_pembayaran_ppdb'] ?? false),
                'pembayaran_ppdb' => $flow['pembayaran_ppdb'] ?? null,
                'step' => $flow['step'] ?? null,
                'flow' => $flow,
            ],
        ]);
    }

    public function infaqPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $idPendaftaran = $request->input('id_pendaftaran') ?? $request->query('id_pendaftaran');
        $pendaftar = $idPendaftaran
            ? $akun->pendaftaran()->where('id_pendaftaran', $idPendaftaran)->first()
            : $akun->pendaftaran()->orderByDesc('id_pendaftaran')->first();

        if (!$pendaftar) {
            return response()->json([
                'message' => 'ID pendaftaran belum dibuat untuk akun ini.',
            ], 422);
        }

        $this->ensurePpdbAdministrasiTagihan($pendaftar);
        $this->ensurePpdbInfaqTagihan($pendaftar);
        $flow = $this->buildPpdbFlowState($pendaftar);

        return response()->json([
            'message' => 'Data infaq PPDB berhasil dimuat.',
            'data' => [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'status_verifikasi' => $pendaftar->status_verifikasi,
                'pilihan_uang_gedung' => $pendaftar->pilihan_uang_gedung ? (int) $pendaftar->pilihan_uang_gedung : null,
                'pilihan_infaq_bulanan' => $pendaftar->pilihan_infaq_bulanan ? (int) $pendaftar->pilihan_infaq_bulanan : null,
                'is_anak_guru' => (bool) $pendaftar->is_anak_guru,
                // POIN 4: Expose bukti ortu/guru agar halaman infaq bisa menampilkan
                // status upload yang sudah tersimpan sebelumnya
                'bukti_ortu_guru_url' => $pendaftar->bukti_ortu_guru_path
                    ? asset('storage/' . $pendaftar->bukti_ortu_guru_path)
                    : null,
                'bukti_ortu_guru_verified' => (bool) $pendaftar->bukti_ortu_guru_verified,
                'flow' => $flow,
                'pembayaran_ppdb' => $flow['pembayaran_ppdb'] ?? null,
            ],
        ]);
    }

    public function updateFormPpdb(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('updateFormPpdb request input: ' . json_encode($request->except(['password'])));
        \Illuminate\Support\Facades\Log::info('updateFormPpdb request files: ' . json_encode(array_map(fn($f) => [
            'name' => $f->getClientOriginalName(),
            'size' => $f->getSize(),
            'mime' => $f->getMimeType(),
        ], $request->allFiles())));
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

        $normalizedPayload = $this->normalizePpdbFormPayload($request);
        if ($normalizedPayload !== []) {
            $request->merge($normalizedPayload);
        }

        $storedFilePaths = $this->storePpdbFormFiles($request);
        if ($storedFilePaths !== []) {
            $request->merge($storedFilePaths);
        }

        $validated = $request->validate([
            'nama_calon' => 'sometimes|nullable|string|max:200',
            'program_pendaftaran' => 'sometimes|nullable|string|max:100',
            'jenjang' => 'sometimes|nullable|string|max:20',
            'jenis_kelamin' => 'sometimes|nullable|in:L,P',
            'tempat_lahir' => 'sometimes|nullable|string|max:100',
            'tanggal_lahir' => 'sometimes|nullable|date',
            'nik_calon_santri' => 'sometimes|nullable|string|max:30',
            'alamat_lengkap' => 'sometimes|nullable|string',
            'riwayat_penyakit' => 'sometimes|nullable|string',
            'nama_ayah' => 'sometimes|nullable|string|max:200',
            'penghasilan_ayah' => 'sometimes|nullable|string|max:100',
            'no_hp_calon' => 'sometimes|nullable|string|max:30',
            'nama_ibu' => 'sometimes|nullable|string|max:200',
            'no_hp_ibu' => 'sometimes|nullable|string|max:30',
            'soal_jawab' => 'sometimes|nullable|string',
            'file_akta_path' => 'sometimes|nullable|string',
            'file_kk_path' => 'sometimes|nullable|string',
            'file_surat_rekomendasi_path' => 'sometimes|nullable|string',
            'surat_pernyataan_setuju' => 'sometimes|nullable|boolean',
            'surat_pernyataan_file_path' => 'sometimes|nullable|string',
            'nomor_umi' => 'sometimes|nullable|string|max:50',
            'asal_kota' => 'sometimes|nullable|string|max:100',
            'phone_ppdb' => 'sometimes|nullable|string|max:30',
            'is_anak_guru' => 'sometimes|nullable|boolean',
            'pilihan_uang_gedung' => 'sometimes|nullable|integer',
            'pilihan_infaq_bulanan' => 'sometimes|nullable|integer',
            'bukti_uang_pangkal_path' => 'sometimes|nullable|string',
            'bukti_spp_path' => 'sometimes|nullable|string',
            'bukti_ortu_guru_path' => 'sometimes|nullable|string',
        ]);

        $jenjangDipakai = mb_strtolower((string) ($validated['jenjang'] ?? $pendaftar->jenjang));
        if (
            $jenjangDipakai === 'smp'
            && array_key_exists('nomor_umi', $validated)
            && blank($validated['nomor_umi'])
        ) {
            throw ValidationException::withMessages([
                'nomor_umi' => ['Nomor UMI wajib diisi untuk jenjang SMP.'],
            ]);
        }

        $updatableFields = [
            'nama_calon',
            'program_pendaftaran',
            'jenjang',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'nik_calon_santri',
            'alamat_lengkap',
            'riwayat_penyakit',
            'nama_ayah',
            'penghasilan_ayah',
            'no_hp_calon',
            'nama_ibu',
            'no_hp_ibu',
            'soal_jawab',
            'file_akta_path',
            'file_kk_path',
            'file_surat_rekomendasi_path',
            'surat_pernyataan_setuju',
            'surat_pernyataan_file_path',
            'nomor_umi',
            'asal_kota',
            'is_anak_guru',
            'pilihan_uang_gedung',
            'pilihan_infaq_bulanan',
            'bukti_uang_pangkal_path',
            'bukti_spp_path',
            'bukti_ortu_guru_path',
        ];

        $updates = [];
        foreach ($updatableFields as $field) {
            if (!array_key_exists($field, $validated)) {
                continue;
            }

            $updates[$field] = $validated[$field];
        }

        if (array_key_exists('asal_kota', $updates)) {
            $updates['is_luar_kota'] = $this->registrationNumberService()->isLuarKota($updates['asal_kota']);
        }

        if (array_key_exists('bukti_uang_pangkal_path', $updates) && !empty($updates['bukti_uang_pangkal_path'])) {
            $updates['status_uang_pangkal'] = 'menunggu_verifikasi';
        }
        if (array_key_exists('bukti_spp_path', $updates) && !empty($updates['bukti_spp_path'])) {
            $updates['status_spp'] = 'menunggu_verifikasi';
        }

        if ($updates !== []) {
            $pendaftar->fill($updates)->save();
            $this->syncPpdbBerkasRecords($pendaftar, $updates);
        }

        if (array_key_exists('phone_ppdb', $validated) && !empty($validated['phone_ppdb'])) {
            $akun->update(['phone' => $validated['phone_ppdb']]);
        }

        // Load period dulu agar ensurePpdbAdministrasiTagihan bisa pakai relasi yang sudah ter-load
        $pendaftar->load(['tes', 'verifikasi', 'period']);
        $this->ensurePpdbAdministrasiTagihan($pendaftar);
        
        // Generate tagihan infaq PPDB jika pilihan sudah diisi
        $this->ensurePpdbInfaqTagihan($pendaftar);

        return response()->json([
            'message' => 'Form pendaftaran berhasil disimpan.',
            'data' => [
                'pendaftaran' => $pendaftar,
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ],
        ]);
    }
    
    /**
     * Generate tagihan infaq PPDB berdasarkan pilihan pendaftar.
     * Tagihan yang dibuat:
     * 1. Uang Pangkal (sesuai pilihan A/B)
     * 2. Perlengkapan (tetap untuk PAUD, Pratahfidz, MTs)
     * 3. Uang Modul (tetap untuk MTs, MTQU)
     * 4. SPP Bulanan (sesuai pilihan A/B) - untuk pembayaran pertama
     */
    protected function ensurePpdbInfaqTagihan(PpdbPendaftar $pendaftar): void
    {
        // Hanya generate jika pilihan infaq sudah diisi dan status diterima
        if (
            !$pendaftar->pilihan_uang_gedung 
            || !$pendaftar->pilihan_infaq_bulanan
            || $pendaftar->status_verifikasi !== 'diterima'
        ) {
            return;
        }

        $jenjang = strtoupper(trim((string) ($pendaftar->jenjang ?? $pendaftar->program_pendaftaran ?? '')));
        
        // Konfigurasi nominal infaq per jenjang
        $configInfaq = $this->getInfaqConfig($jenjang);
        
        if (!$configInfaq) {
            \Log::warning("Konfigurasi infaq tidak ditemukan untuk jenjang: {$jenjang}");
            return;
        }

        // Nominal yang dipilih pendaftar
        $nominalUangPangkal = $pendaftar->pilihan_uang_gedung == 1 
            ? $configInfaq['uang_pangkal_a'] 
            : $configInfaq['uang_pangkal_b'];
        
        $nominalInfaqBulanan = $pendaftar->pilihan_infaq_bulanan == 1
            ? $configInfaq['infaq_bulanan_a']
            : $configInfaq['infaq_bulanan_b'];
        
        // Apply diskon anak guru HANYA untuk Uang Pangkal
        if ($pendaftar->is_anak_guru) {
            $nominalUangPangkal = $nominalUangPangkal * 0.5; // 50% diskon
        }
        
        // Issue #9: Gabungkan Uang Pangkal + Perlengkapan + SPP Bulanan Pertama menjadi SATU tagihan
        $totalTagihanGabungan = $nominalUangPangkal + $configInfaq['perlengkapan'] + $nominalInfaqBulanan;
        $this->createOrUpdateTagihan(
            $pendaftar,
            'Uang Gedung + Perlengkapan + SPP Pertama',
            $totalTagihanGabungan,
            'PPDB_UANG_PANGKAL_SPP'
        );
        
        // Buat tagihan Uang Modul terpisah (jika ada)
        if ($configInfaq['uang_modul'] > 0) {
            $this->createOrUpdateTagihan(
                $pendaftar,
                'Uang Modul Semester Ganjil',
                $configInfaq['uang_modul'],
                'PPDB_UANG_MODUL'
            );
        }
    }
    
    /**
     * Get konfigurasi infaq berdasarkan jenjang
     */
    protected function getInfaqConfig(string $jenjang): ?array
    {
        $configs = [
            'PAUD' => [
                'uang_pangkal_a' => 1_000_000,
                'uang_pangkal_b' => 1_500_000,
                'perlengkapan' => 300_000,
                'uang_modul' => 0,
                'infaq_bulanan_a' => 200_000,
                'infaq_bulanan_b' => 250_000,
            ],
            'PRATAHFIDZ' => [
                'uang_pangkal_a' => 1_000_000,
                'uang_pangkal_b' => 1_500_000,
                'perlengkapan' => 1_200_000,
                'uang_modul' => 0,
                'infaq_bulanan_a' => 300_000,
                'infaq_bulanan_b' => 350_000,
            ],
            'MTS' => [
                'uang_pangkal_a' => 1_500_000,
                'uang_pangkal_b' => 2_000_000,
                'perlengkapan' => 875_000, // meja, sekat, almari, kasur
                'uang_modul' => 250_000,
                'infaq_bulanan_a' => 600_000,
                'infaq_bulanan_b' => 650_000,
            ],
            'MA' => [
                'uang_pangkal_a' => 1_500_000,
                'uang_pangkal_b' => 2_000_000,
                'perlengkapan' => 875_000,
                'uang_modul' => 250_000,
                'infaq_bulanan_a' => 650_000,
                'infaq_bulanan_b' => 700_000,
            ],
            'MTQU' => [
                'uang_pangkal_a' => 1_800_000,
                'uang_pangkal_b' => 2_000_000,
                'perlengkapan' => 0,
                'uang_modul' => 200_000,
                'infaq_bulanan_a' => 350_000,
                'infaq_bulanan_b' => 400_000,
            ],
        ];
        
        return $configs[$jenjang] ?? null;
    }
    
    /**
     * Create or update tagihan dengan FirstOrCreate untuk idempotency
     */
    protected function createOrUpdateTagihan(
        PpdbPendaftar $pendaftar, 
        string $keterangan, 
        float $nominal, 
        string $jenis
    ): PembayaranSpp {
        // Cari atau buat setting yang sesuai
        $setting = SppSetting::firstOrCreate(
            [
                'jenjang' => $pendaftar->jenjang,
                'keterangan' => $keterangan,
            ],
            [
                'jumlah' => $nominal,
                'aktif' => true,
            ]
        );
        
        // Hitung DP 50%
        $minimumDp = $nominal * 0.5;
        
        // Create or update tagihan (idempotent)
        $tagihan = PembayaranSpp::firstOrCreate(
            [
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'id_setting' => $setting->id_setting,
            ],
            [
                'id_santri' => $pendaftar->id_santri,
                'nominal_bayar' => $nominal,
                'status' => 'menunggu_pembayaran',
                'metode_bayar' => null,
                'tanggal_bayar' => null,
            ]
        );
        
        // Tambahkan field custom untuk DP jika belum ada
        if (!$tagihan->wasRecentlyCreated && $tagihan->status === 'menunggu_pembayaran') {
            // Update nominal jika ada perubahan
            if ($tagihan->nominal_bayar != $nominal) {
                $tagihan->update(['nominal_bayar' => $nominal]);
            }
        }
        
        return $tagihan;
    }

    protected function ensurePpdbAdministrasiTagihan(PpdbPendaftar $pendaftar): void
    {
        if (!$pendaftar->id_pendaftaran) {
            return;
        }

        // Ambil biaya dari gelombang PPDB yang terkait
        $biayaGelombang = null;
        if ($pendaftar->ppdb_period_id) {
            $period = $pendaftar->relationLoaded('period')
                ? $pendaftar->period
                : \App\Models\PpdbPeriod::find($pendaftar->ppdb_period_id);
            $biayaGelombang = $period?->biaya_pendaftaran ? (int) $period->biaya_pendaftaran : null;
        }

        // Fallback ke nilai default jika gelombang tidak memiliki biaya
        $nominalDefault = $pendaftar->is_anak_guru ? 50000 : 100000;

        if ($biayaGelombang !== null && $biayaGelombang > 0) {
            // Anak guru mendapat diskon 50% dari biaya gelombang
            $nominal = $pendaftar->is_anak_guru ? (int) round($biayaGelombang * 0.5) : $biayaGelombang;
        } else {
            $nominal = $nominalDefault;
        }

        $tagihan = PembayaranSpp::query()
            ->where('id_pendaftaran', $pendaftar->id_pendaftaran)
            ->where('metode_bayar', 'administrasi')
            ->first();

        if ($tagihan) {
            if ($tagihan->status === 'menunggu_pembayaran' && (int)$tagihan->nominal_bayar !== $nominal) {
                $tagihan->update(['nominal_bayar' => $nominal]);
            }
            return;
        }

        PembayaranSpp::create([
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'id_santri' => $pendaftar->id_santri,
            'nominal_bayar' => $nominal,
            'status' => 'menunggu_pembayaran',
            'metode_bayar' => 'administrasi',
        ]);
    }

    protected function normalizePpdbFormPayload(Request $request): array
    {
        $payload = [];

        $namaCalon = trim((string) (
            $request->input('nama_calon')
            ?? $request->input('nama_lengkap')
            ?? $request->input('nama')
            ?? ''
        ));
        if ($namaCalon !== '') {
            $payload['nama_calon'] = $namaCalon;
        }

        $programPendaftaran = trim((string) (
            $request->input('program_pendaftaran')
            ?? $request->input('program')
            ?? $request->input('program_daftar')
            ?? ''
        ));

        if ($programPendaftaran === '') {
            $programPendaftaran = trim((string) $request->input('jenjang', ''));
        }

        if ($programPendaftaran !== '') {
            $payload['program_pendaftaran'] = $programPendaftaran;
            if (!$request->filled('jenjang')) {
                $payload['jenjang'] = $programPendaftaran;
            }
        }

        if (!$request->filled('jenjang') && $request->filled('programPendaftaran')) {
            $payload['jenjang'] = trim((string) $request->input('programPendaftaran'));
        }

        $jenisKelaminInput = trim((string) (
            $request->input('jenis_kelamin')
            ?? $request->input('jenisKelamin')
            ?? ''
        ));

        if ($jenisKelaminInput !== '') {
            $normalizedJenisKelamin = mb_strtolower($jenisKelaminInput);

            if (in_array($normalizedJenisKelamin, ['l', 'lk', 'laki', 'laki-laki', 'laki laki', 'pria', 'male', 'm'], true)) {
                $payload['jenis_kelamin'] = 'L';
            } elseif (in_array($normalizedJenisKelamin, ['p', 'pr', 'perempuan', 'wanita', 'female', 'f'], true)) {
                $payload['jenis_kelamin'] = 'P';
            }
        }

        $nikCalonSantri = trim((string) (
            $request->input('nik_calon_santri')
            ?? $request->input('nik')
            ?? ''
        ));
        if ($nikCalonSantri !== '') {
            $payload['nik_calon_santri'] = $nikCalonSantri;
        }

        $alamatLengkap = trim((string) (
            $request->input('alamat_lengkap')
            ?? $request->input('alamat')
            ?? ''
        ));
        if ($alamatLengkap !== '') {
            $payload['alamat_lengkap'] = $alamatLengkap;
        }

        if (!$request->filled('tempat_lahir') && $request->filled('tempatLahir')) {
            $payload['tempat_lahir'] = trim((string) $request->input('tempatLahir'));
        }

        if (!$request->filled('tanggal_lahir') && $request->filled('tanggalLahir')) {
            $payload['tanggal_lahir'] = trim((string) $request->input('tanggalLahir'));
        }

        if (!$request->filled('riwayat_penyakit') && $request->filled('riwayatPenyakit')) {
            $payload['riwayat_penyakit'] = trim((string) $request->input('riwayatPenyakit'));
        }

        if (!$request->filled('nama_ayah') && $request->filled('namaAyah')) {
            $payload['nama_ayah'] = trim((string) $request->input('namaAyah'));
        }

        if (!$request->filled('penghasilan_ayah') && $request->filled('penghasilanAyah')) {
            $payload['penghasilan_ayah'] = trim((string) $request->input('penghasilanAyah'));
        }

        if (!$request->filled('nama_ibu') && $request->filled('namaIbu')) {
            $payload['nama_ibu'] = trim((string) $request->input('namaIbu'));
        }

        if (!$request->filled('no_hp_ibu') && $request->filled('noHpIbu')) {
            $payload['no_hp_ibu'] = trim((string) $request->input('noHpIbu'));
        }

        if (!$request->filled('soal_jawab') && $request->filled('soalJawab')) {
            $payload['soal_jawab'] = trim((string) $request->input('soalJawab'));
        }

        if (!$request->filled('nomor_umi') && $request->filled('nomorUmi')) {
            $payload['nomor_umi'] = trim((string) $request->input('nomorUmi'));
        }

        if (!$request->filled('asal_kota') && $request->filled('asalKota')) {
            $payload['asal_kota'] = trim((string) $request->input('asalKota'));
        }

        $asalSekolah = trim((string) (
            $request->input('asal_sekolah')
            ?? $request->input('asalSekolah')
            ?? ''
        ));
        if (!$request->filled('asal_kota') && $asalSekolah !== '') {
            $payload['asal_kota'] = $asalSekolah;
        }

        $noHpCalon = trim((string) (
            $request->input('no_hp_calon')
            ?? $request->input('no_hp_ayah')
            ?? $request->input('noHpCalon')
            ?? $request->input('noHpAyah')
            ?? $request->input('phone_ppdb')
            ?? $request->input('phonePpdb')
            ?? $request->input('phone')
            ?? ''
        ));
        if ($noHpCalon !== '') {
            $payload['no_hp_calon'] = $noHpCalon;
        }

        if (!$request->filled('phone_ppdb') && $request->filled('phonePpdb')) {
            $payload['phone_ppdb'] = trim((string) $request->input('phonePpdb'));
        }

        $suratPernyataanSetuju = $request->input('surat_pernyataan_setuju');
        if ($suratPernyataanSetuju === null) {
            $suratPernyataanSetuju = $request->input('suratPernyataanSetuju');
        }
        if (is_string($suratPernyataanSetuju)) {
            $normalizedValue = mb_strtolower(trim($suratPernyataanSetuju));

            if (in_array($normalizedValue, ['accepted', '1', 'true', 'yes', 'y', 'on'], true)) {
                $payload['surat_pernyataan_setuju'] = true;
            } elseif (in_array($normalizedValue, ['0', 'false', 'no', 'n', 'off'], true)) {
                $payload['surat_pernyataan_setuju'] = false;
            }
        }

        // Normalize is_anak_guru (camelCase alias)
        if (!$request->has('is_anak_guru') && $request->has('isAnakGuru')) {
            $rawAnakGuru = $request->input('isAnakGuru');
            if (is_string($rawAnakGuru)) {
                $payload['is_anak_guru'] = in_array(mb_strtolower(trim($rawAnakGuru)), ['1', 'true', 'yes', 'y', 'on'], true);
            } elseif (is_bool($rawAnakGuru)) {
                $payload['is_anak_guru'] = $rawAnakGuru;
            }
        }

        // Normalize pilihan_uang_gedung (camelCase alias)
        if (!$request->has('pilihan_uang_gedung') && $request->has('pilihanUangGedung')) {
            $v = (int) $request->input('pilihanUangGedung');
            if (in_array($v, [1, 2], true)) {
                $payload['pilihan_uang_gedung'] = $v;
            }
        }

        // Normalize pilihan_infaq_bulanan (camelCase alias)
        if (!$request->has('pilihan_infaq_bulanan') && $request->has('pilihanInfaqBulanan')) {
            $v = (int) $request->input('pilihanInfaqBulanan');
            if (in_array($v, [1, 2], true)) {
                $payload['pilihan_infaq_bulanan'] = $v;
            }
        }

        return $payload;
    }

    protected function storePpdbFormFiles(Request $request): array
    {
        $storedPaths = [];

        $storeByAliases = function (array $aliases, string $targetField) use ($request, &$storedPaths): bool {
            foreach ($aliases as $alias) {
                if ($request->hasFile($alias)) {
                    $storedPaths[$targetField] = $request->file($alias)->store('ppdb/berkas', 'public');
                    return true;
                }
            }

            return false;
        };

        $aktaStored = $storeByAliases(['akta', 'berkas_akta', 'dokumen_akta', 'dokumenAkta'], 'file_akta_path');
        $kkStored = $storeByAliases(['kk', 'berkas_kk', 'dokumen_kk', 'dokumenKk'], 'file_kk_path');

        $aktaKkFile = null;
        foreach (['akta_kk', 'dokumen_akta_kk', 'dokumenAktaKk'] as $alias) {
            if (!$request->hasFile($alias)) {
                continue;
            }

            $aktaKkFile = $request->file($alias);
            break;
        }

        if ($aktaKkFile) {
            $aktaKkPath = $aktaKkFile->store('ppdb/berkas', 'public');

            if (!$aktaStored) {
                $storedPaths['file_akta_path'] = $aktaKkPath;
            }

            if (!$kkStored) {
                $storedPaths['file_kk_path'] = $aktaKkPath;
            }
        }

        $storeByAliases(
            [
                'surat_rekomendasi_ustadz',
                'berkas_rekomendasi_ustadz',
                'dokumen_rekomendasi_ustadz',
                'dokumenRekomendasiUstadz',
            ],
            'file_surat_rekomendasi_path'
        );

        $storeByAliases(
            [
                'surat_pernyataan_file',
                'berkas_surat_pernyataan',
                'dokumen_surat_pernyataan',
                'dokumenSuratPernyataan',
            ],
            'surat_pernyataan_file_path'
        );

        $storeByAliases(['bukti_uang_pangkal', 'buktiUangPangkal'], 'bukti_uang_pangkal_path');
        $storeByAliases(['bukti_spp', 'buktiSpp'], 'bukti_spp_path');
        $storeByAliases(['bukti_ortu_guru', 'buktiOrtuGuru'], 'bukti_ortu_guru_path');

        return $storedPaths;
    }

    protected function syncPpdbBerkasRecords(PpdbPendaftar $pendaftar, array $payload): void
    {
        $jenisByField = [
            'file_akta_path' => 'akta',
            'file_kk_path' => 'kk',
            'file_surat_rekomendasi_path' => 'surat_rekomendasi',
            'surat_pernyataan_file_path' => 'surat_pernyataan',
            // POIN 4: Sync bukti anak guru ke tabel ppdb_berkas agar konsisten
            'bukti_ortu_guru_path' => 'bukti_ortu_guru',
        ];

        foreach ($jenisByField as $field => $jenisBerkas) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $filePath = trim((string) ($payload[$field] ?? ''));
            if ($filePath === '') {
                continue;
            }

            PpdbBerkas::updateOrCreate(
                [
                    'id_pendaftaran' => $pendaftar->id_pendaftaran,
                    'jenis_berkas' => $jenisBerkas,
                ],
                [
                    'file_path' => $filePath,
                    'uploaded_at' => now(),
                ]
            );
        }
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
            'role'     => 'required|in:petugas,santri',
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($request->role === 'petugas') {
            $user  = DataPetugas::where('alamat_email', $request->username)->first();
            $guard = 'petugas';
        } else {
            $user  = DataAkunSantri::where('nama_akun', $request->username)
                ->orWhere('nomor_induk', $request->username)
                ->orWhere('alamat_email', $request->username)
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
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.',
            ], 403);
        }

        // ── OTP Step ────────────────────────────────────────────────────────────
        // Aktifkan via .env: OTP_ENABLED=true
        $otpEnabled = (bool) config('app.otp_enabled', false);
        $phone = $user->nomor_telepon ?? null;

        if ($otpEnabled && $phone) {
            $identifier = (string) $user->getKey();
            /** @var \App\Support\OtpService $otpService */
            $otpService = app(\App\Support\OtpService::class);
            $otpService->generateAndSend($identifier, $guard, $phone);

            return response()->json([
                'message'      => 'OTP telah dikirim ke WhatsApp Anda. Masukkan kode untuk melanjutkan.',
                'otp_required' => true,
                'otp_context'  => [
                    'identifier' => $identifier,
                    'guard'      => $guard,
                ],
            ]);
        }
        // ── End OTP Step ────────────────────────────────────────────────────────

        Auth::guard($guard)->login($user);
        $request->session()->regenerate();

        if (in_array($guard, ['petugas', 'santri'], true)) {
            $user->update(['last_login' => now()]);
        }

        // Issue Sanctum API token
        $user->tokens()->delete();
        $accessToken = $user->createToken("{$guard}-api")->plainTextToken;

        $userPayload = [
            'id'          => $user->getKey(),
            'nama_lengkap'=> $user->nama_lengkap ?? $user->nama,
            'email'       => $user->alamat_email ?? $user->email,
            'nomor_induk' => $user->nomor_induk ?? null,
            'id_santri'   => $guard === 'santri' ? ($user->santri->id_santri ?? null) : null,
            'peran_akun'  => $user->peran_akun ?? null,
            'pilihan_unit'=> $user->pilihan_unit ?? null,
        ];

        return response()->json([
            'message'      => 'Login Berhasil!',
            'role'         => $guard,
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => $userPayload,
        ]);
    }

    /**
     * Verifikasi OTP setelah login — issue token Sanctum jika kode valid.
     * POST /api/otp/verify
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'guard'      => 'required|in:petugas,santri',
            'kode'       => 'required|string|size:6',
        ]);

        /** @var \App\Support\OtpService $otpService */
        $otpService = app(\App\Support\OtpService::class);
        $valid = $otpService->verify(
            $validated['identifier'],
            $validated['guard'],
            $validated['kode']
        );

        if (!$valid) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        // Ambil user & issue token
        $guard = $validated['guard'];
        if ($guard === 'petugas') {
            $user = DataPetugas::findOrFail($validated['identifier']);
        } else {
            $user = DataAkunSantri::findOrFail($validated['identifier']);
        }

        $user->tokens()->delete();
        $accessToken = $user->createToken("{$guard}-api")->plainTextToken;

        if (in_array($guard, ['petugas', 'santri'], true)) {
            $user->update(['last_login' => now()]);
        }

        $userPayload = [
            'id'          => $user->getKey(),
            'nama_lengkap'=> $user->nama_lengkap ?? $user->nama,
            'email'       => $user->alamat_email ?? $user->email,
            'nomor_induk' => $user->nomor_induk ?? null,
            'id_santri'   => $guard === 'santri' ? ($user->santri->id_santri ?? null) : null,
            'peran_akun'  => $user->peran_akun ?? null,
            'pilihan_unit'=> $user->pilihan_unit ?? null,
        ];

        return response()->json([
            'message'      => 'Login Berhasil!',
            'role'         => $guard,
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => $userPayload,
        ]);
    }

    public function logout(Request $request)
    {
        $apiUser = $request->user();

        if ($apiUser && method_exists($apiUser, 'currentAccessToken')) {
            $currentAccessToken = $apiUser->currentAccessToken();
            if ($currentAccessToken && method_exists($currentAccessToken, 'delete')) {
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

        $tanggalDaftar = Carbon::now();
        $nomorService = $this->registrationNumberService();
        $idPendaftaran = $nomorService->generatePendaftaranId($tanggalDaftar);
        $noPendaftaran = $nomorService->generateInitialNumber($tanggalDaftar);
        $noPendaftaranFinal = $nomorService->generateFinalNumber($noPendaftaran);

        $namaDraft = trim((string) ($akun->nama ?? ''));
        if ($namaDraft === '') {
            $email = (string) ($akun->email ?? '');
            $namaDraft = $email !== '' ? explode('@', $email)[0] : 'Calon Peserta';
        }

        $activePeriod = PpdbPeriod::sedangBerlangsung()->first();

        $pendaftar = new PpdbPendaftar([
            'id_akun' => $akun->id_akun,
            'ppdb_period_id' => $activePeriod ? $activePeriod->id : null,
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

    protected function createNewDraftPendaftaran(AkunPendaftar $akun): PpdbPendaftar
    {
        $tanggalDaftar = Carbon::now();
        $nomorService = $this->registrationNumberService();
        $idPendaftaran = $nomorService->generatePendaftaranId($tanggalDaftar);
        $noPendaftaran = $nomorService->generateInitialNumber($tanggalDaftar);
        $noPendaftaranFinal = $nomorService->generateFinalNumber($noPendaftaran);

        $activePeriod = PpdbPeriod::sedangBerlangsung()->first();

        $pendaftar = new PpdbPendaftar([
            'id_akun' => $akun->id_akun,
            'ppdb_period_id' => $activePeriod ? $activePeriod->id : null,
            'no_pendaftaran' => $noPendaftaran,
            'no_pendaftaran_final' => $noPendaftaranFinal,
            'nama_calon' => 'Calon Peserta Baru',
            'status_verifikasi' => 'pending',
            'tanggal_daftar' => $tanggalDaftar->toDateString(),
            'waktu_pendaftaran' => $tanggalDaftar,
            'is_luar_kota' => false,
        ]);

        $pendaftar->id_pendaftaran = $idPendaftaran;
        $pendaftar->save();

        return $pendaftar;
    }

    public function tambahSiswaPpdb(Request $request)
    {
        $akun = $this->resolveAuthenticatedPpdbUser();

        if (!$akun) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $activePeriod = PpdbPeriod::sedangBerlangsung()->first();
        if (!$activePeriod) {
            return response()->json([
                'message' => 'Pendaftaran PPDB saat ini sedang ditutup atau belum dibuka.',
            ], 422);
        }

        if ($activePeriod->isKuotaPenuh()) {
            return response()->json([
                'message' => 'Kuota pendaftaran untuk gelombang saat ini sudah penuh.',
            ], 422);
        }

        $pendaftar = $this->createNewDraftPendaftaran($akun);

        return response()->json([
            'message' => 'Pendaftaran untuk saudara/siswa baru berhasil dibuat.',
            'data' => [
                'id_akun' => $akun->id_akun,
                'email_ppdb' => $akun->email,
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'nomor_pendaftar' => $pendaftar->no_pendaftaran,
                'no_pendaftaran' => $pendaftar->no_pendaftaran,
                'no_pendaftaran_final' => $pendaftar->no_pendaftaran_final,
                'status_verifikasi' => $pendaftar->status_verifikasi,
                'tanggal_daftar' => $pendaftar->tanggal_daftar,
            ]
        ], 201);
    }

    protected function buildPpdbFlowState(PpdbPendaftar $pendaftar): array
    {
        $verifikasi = $pendaftar->relationLoaded('verifikasi') ? $pendaftar->verifikasi : $pendaftar->verifikasi()->first();

        $konfigurasiTes = $this->resolveTesKonfigurasiForPendaftar($pendaftar);
        $fiturSoalAktif = (bool) ($konfigurasiTes?->fitur_soal_aktif ?? false);
        $bahasa = $konfigurasiTes?->bahasa ?? 'id';
        $isRtl = (bool) ($konfigurasiTes?->is_rtl ?? false);
        $soalTesRaw = trim((string) ($konfigurasiTes?->soal_tes ?? ''));
        $soalTesDecoded = json_decode($soalTesRaw, true);
        $soalTes = (json_last_error() === JSON_ERROR_NONE) ? $soalTesDecoded : $soalTesRaw;
        $formSchema = is_array($konfigurasiTes?->form_schema) ? $konfigurasiTes->form_schema : [];

        $tesAvailable = $fiturSoalAktif && (
            $soalTesRaw !== '' || (is_array($formSchema) && count($formSchema) > 0)
        );

        $isFormLengkap = $this->isPpdbFormLengkapUntukTes($pendaftar);
        $tesSubmitted = trim((string) ($pendaftar->soal_jawab ?? '')) !== '';
        $tesSelesai = $tesSubmitted;

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

        $showTesPage = $tesAvailable && $isFormLengkap && !$tesSelesai;
        $showTanggalPengumuman = !$showTesPage;
        $showFormPengumuman = !$showTesPage && $isPengumumanDibuka;
        $pendaftaranSelesai = $isFormLengkap && (!$tesAvailable || $tesSelesai);
        $pembayaranPpdb = $this->resolvePpdbPaymentInfo($pendaftar);
        $showPembayaranPpdb = $pendaftaranSelesai;

        $isStatusDiterima = in_array($statusVerifikasi, ['diterima', 'lulus', 'accepted'], true);
        $isPaymentVerified = ($pembayaranPpdb['status'] ?? null) === 'terverifikasi';

        // ── Post-acceptance deadline checks ──────────────────────────────
        $now = now();
        $uangPangkalOverdue = false;
        $sppOverdue = false;

        if ($isStatusDiterima && $pendaftar->batas_bayar_uang_pangkal) {
            $uangPangkalOverdue = $now->startOfDay()->greaterThan(
                Carbon::parse($pendaftar->batas_bayar_uang_pangkal)->endOfDay()
            );
        }

        if ($isStatusDiterima && $pendaftar->batas_bayar_spp) {
            $sppOverdue = $now->startOfDay()->greaterThan(
                Carbon::parse($pendaftar->batas_bayar_spp)->endOfDay()
            );
        }

        $statusUangPangkal = $pendaftar->status_uang_pangkal;
        $statusSpp = $pendaftar->status_spp;

        // Auto-mark as 'gagal' if deadline passed and not yet paid
        $mustSave = false;
        if ($uangPangkalOverdue && !in_array($statusUangPangkal, ['dp', 'lunas'], true)) {
            $statusUangPangkal = 'gagal';
            if ($pendaftar->status_uang_pangkal !== 'gagal') {
                $pendaftar->status_uang_pangkal = 'gagal';
                $mustSave = true;
            }
            if ($pendaftar->status_verifikasi !== 'tidak_diterima') {
                $pendaftar->status_verifikasi = 'tidak_diterima';
                $mustSave = true;
            }
        }
        if ($sppOverdue && !in_array($statusSpp, ['dp', 'lunas'], true)) {
            $statusSpp = 'gagal';
            if ($pendaftar->status_spp !== 'gagal') {
                $pendaftar->status_spp = 'gagal';
                $mustSave = true;
            }
            if ($pendaftar->status_verifikasi !== 'tidak_diterima') {
                $pendaftar->status_verifikasi = 'tidak_diterima';
                $mustSave = true;
            }
        }
        if ($mustSave) {
            $pendaftar->save();
            $statusVerifikasi = 'tidak_diterima';
            $isStatusDiterima = false;
        }

        // ── Step determination ───────────────────────────────────────────
        $infaqStepRequired = false;
        $showPembayaranPpdb = false;

        $step = 'lengkapi-form';
        if ($showTesPage) {
            $step = 'tes';
        } elseif ($pendaftaranSelesai && $isPengumumanDibuka) {
            $step = 'pengumuman';
        } elseif ($pendaftaranSelesai) {
            $step = 'menunggu-pengumuman';
        }

        return [
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'show_form_pendaftaran' => true,
            'show_halaman_tes' => $showTesPage,
            'show_halaman_tanggal_pengumuman' => $showTanggalPengumuman,
            'show_form_pengumuman' => $showFormPengumuman,
            'tanggal_pengumuman' => $tanggalPengumuman,
            'is_pengumuman_dibuka' => $isPengumumanDibuka,
            'fitur_soal_aktif' => $fiturSoalAktif,
            'tes_required' => $tesAvailable,
            'tes_available' => $tesAvailable,
            'tes_submitted' => $tesSubmitted,
            'tes_finished' => $tesSelesai,
            'is_form_lengkap' => $isFormLengkap,
            'pendaftaran_selesai' => $pendaftaranSelesai,
            'infaq_step_required' => $infaqStepRequired,
            'show_halaman_pembayaran_ppdb' => $showPembayaranPpdb,
            'pembayaran_ppdb' => $pembayaranPpdb,
            'soal_tes' => $soalTes,
            'form_schema' => $formSchema,
            'bahasa' => $bahasa,
            'is_rtl' => $isRtl,
            'step' => $step,
            'status_verifikasi' => $pendaftar->status_verifikasi,
            'is_anak_guru' => (bool) $pendaftar->is_anak_guru,
            'pilihan_uang_gedung' => $pendaftar->pilihan_uang_gedung ? (int)$pendaftar->pilihan_uang_gedung : null,
            'pilihan_infaq_bulanan' => $pendaftar->pilihan_infaq_bulanan ? (int)$pendaftar->pilihan_infaq_bulanan : null,
            'tanggal_diterima' => optional($pendaftar->tanggal_diterima)?->toDateString(),
            'batas_bayar_uang_pangkal' => optional($pendaftar->batas_bayar_uang_pangkal)?->toDateString(),
            'batas_bayar_spp' => optional($pendaftar->batas_bayar_spp)?->toDateString(),
            'status_uang_pangkal' => $statusUangPangkal,
            'status_spp' => $statusSpp,
            'bukti_uang_pangkal_url' => $pendaftar->bukti_uang_pangkal_path ? asset('storage/' . $pendaftar->bukti_uang_pangkal_path) : null,
            'bukti_spp_url' => $pendaftar->bukti_spp_path ? asset('storage/' . $pendaftar->bukti_spp_path) : null,
            'bukti_ortu_guru_url' => $pendaftar->bukti_ortu_guru_path ? asset('storage/' . $pendaftar->bukti_ortu_guru_path) : null,
            'bukti_ortu_guru_verified' => (bool) $pendaftar->bukti_ortu_guru_verified,
            'nomor_induk_generated' => $pendaftar->nomor_induk_generated,
            'kode_kelas_diterima' => $pendaftar->kode_kelas_diterima,
        ];
    }

    protected function resolveTesKonfigurasiForPendaftar(PpdbPendaftar $pendaftar): ?PpdbTesKonfigurasi
    {
        $jenjang = $this->normalizeJenjangKonfigurasiKey(
            (string) ($pendaftar->jenjang ?: $pendaftar->program_pendaftaran)
        );

        if (!$jenjang) {
            return null;
        }

        return PpdbTesKonfigurasi::query()
            ->where('jenjang', $jenjang)
            ->first();
    }

    protected function normalizeJenjangKonfigurasiKey(string $value): ?string
    {
        $raw = mb_strtoupper(trim($value));
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';

        if ($normalized === 'MI' || str_contains($normalized, 'IBTIDAIYAH') || $normalized === 'SD') {
            return 'MI';
        }

        if ($normalized === 'MTS' || str_contains($normalized, 'TSANAWIYAH') || $normalized === 'SMP') {
            return 'MTS';
        }

        if ($normalized === 'MA' || str_contains($normalized, 'ALIYAH') || $normalized === 'SMA') {
            return 'MA';
        }

        return in_array($normalized, ['MI', 'MTS', 'MA'], true) ? $normalized : null;
    }

    protected function isPpdbFormLengkapUntukTes(PpdbPendaftar $pendaftar): bool
    {
        $jenjang = $this->normalizeJenjangKonfigurasiKey(
            (string) ($pendaftar->jenjang ?: $pendaftar->program_pendaftaran)
        );

        $required = [
            trim((string) $pendaftar->nama_calon) !== '',
            $jenjang !== null,
            trim((string) $pendaftar->nik_calon_santri) !== '',
            trim((string) $pendaftar->alamat_lengkap) !== '',
            trim((string) $pendaftar->tempat_lahir) !== '',
            !empty($pendaftar->tanggal_lahir),
            trim((string) $pendaftar->nama_ayah) !== '',
            trim((string) $pendaftar->no_hp_calon) !== '',
            trim((string) $pendaftar->nama_ibu) !== '',
            trim((string) $pendaftar->no_hp_ibu) !== '',
            // Semua berkas wajib harus sudah diupload sebelum form dianggap lengkap
            trim((string) $pendaftar->file_akta_path) !== '',
            trim((string) $pendaftar->file_kk_path) !== '',
            trim((string) $pendaftar->file_surat_rekomendasi_path) !== '',
            trim((string) $pendaftar->surat_pernyataan_file_path) !== '',
        ];

        if (in_array($jenjang, ['MI', 'MTS', 'MA'], true)) {
            $required[] = trim((string) $pendaftar->asal_kota) !== '';
        }

        return !in_array(false, $required, true);
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

    protected function resolvePpdbPaymentInfo(PpdbPendaftar $pendaftar): array
    {
        // Tagihan administrasi PPDB (biaya pendaftaran/registrasi) selalu bertipe 'administrasi'.
        // Jangan ambil tagihan terbaru secara keseluruhan — setelah pendaftar diterima,
        // tagihan uang pangkal & SPP akan dibuat dan memiliki id_pembayaran lebih besar.
        $adminPayment = PembayaranSpp::query()
            ->with(['kwitansi'])
            ->where('id_pendaftaran', $pendaftar->id_pendaftaran)
            ->where('metode_bayar', 'administrasi')
            ->orderByDesc('id_pembayaran')
            ->first();

        if (!$adminPayment) {
            return [
                'has_tagihan' => false,
                'status' => null,
                'nominal_bayar' => null,
                'tanggal_bayar' => null,
                'tanggal_verifikasi' => null,
                'metode_bayar' => null,
                'kwitansi_tersedia' => false,
            ];
        }

        return [
            'has_tagihan' => true,
            'id_pembayaran' => $adminPayment->id_pembayaran,
            'status' => $adminPayment->status,
            'nominal_bayar' => $adminPayment->nominal_bayar,
            'tanggal_bayar' => optional($adminPayment->tanggal_bayar)->format('Y-m-d H:i:s'),
            'tanggal_verifikasi' => optional($adminPayment->tanggal_verifikasi)->format('Y-m-d H:i:s'),
            'metode_bayar' => $adminPayment->metode_bayar,
            'kwitansi_tersedia' => (bool) $adminPayment->kwitansi,
            'kwitansi' => $adminPayment->kwitansi ? [
                'id_kwitansi' => $adminPayment->kwitansi->id_kwitansi,
                'file_path_pdf' => $adminPayment->kwitansi->file_path_pdf,
                'jumlah' => $adminPayment->kwitansi->jumlah,
            ] : null,
        ];
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
        } else {
            $user = Auth::user();
            if ($user instanceof DataPetugas) {
                $guard = 'petugas';
            } elseif ($user instanceof DataAkunSantri) {
                $guard = 'santri';
            } elseif ($user instanceof AkunPendaftar) {
                $guard = 'ppdb';
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($guard === 'ppdb' && $user instanceof AkunPendaftar) {
            $idPendaftaran = $request->query('id_pendaftaran');
            $pendaftar = $idPendaftaran
                ? $user->pendaftaran()->where('id_pendaftaran', $idPendaftaran)->first()
                : $user->pendaftaran()->orderByDesc('id_pendaftaran')->first();

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

            $daftarPendaftaran = $user->pendaftaran()
                ->orderByDesc('id_pendaftaran')
                ->get()
                ->map(fn ($item) => [
                    'id_pendaftaran' => $item->id_pendaftaran,
                    'nama_calon' => $item->nama_calon,
                    'jenjang' => $item->jenjang,
                    'no_pendaftaran' => $item->no_pendaftaran,
                    'no_pendaftaran_final' => $item->no_pendaftaran_final,
                    'nomor_induk_generated' => $item->nomor_induk_generated,
                    'status_verifikasi' => $item->status_verifikasi,
                ])
                ->values();

            return response()->json([
                'user' => [
                    'id' => $user->getKey(),
                    'id_akun' => $user->id_akun,
                    'nama_lengkap' => $user->nama,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'pendaftaran_aktif' => $pendaftar,
                    'daftar_pendaftaran' => $daftarPendaftaran,
                    'flow' => $this->buildPpdbFlowState($pendaftar),
                ],
                'role' => $guard,
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
                'pendaftaran_aktif' => $pendaftar,
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ]);
        }

        if ($guard === 'santri' && $user instanceof DataAkunSantri) {
            return response()->json([
                'user' => [
                    'id' => $user->getKey(),
                    'id_santri' => $user->santri->id_santri ?? null,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->alamat_email,
                    'nomor_telepon' => $user->nomor_telepon,
                    'nomor_induk' => $user->nomor_induk,
                    'nama_unit' => $user->nama_unit,
                    'nama_kelas' => $user->santri?->kelas?->nama_kelas ?? $user->nama_kelas,
                    'tahun_ajaran' => $user->tahun_ajaran,
                    'jenis_kelamin' => $user->santri->jenis_kelamin ?? null,
                ],
                'role' => $guard
            ]);
        }

        return response()->json([
            'user' => $user,
            'role' => $guard
        ]);
    }

    public function forgotPasswordPpdb(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:akun_pendaftar,email',
        ]);

        $user = AkunPendaftar::where('email', $request->email)->firstOrFail();

        $otpService = app(\App\Support\OtpService::class);
        $otpService->generateAndSend((string) $user->id_akun, 'ppdb_reset', $user->email);

        $otp = \App\Models\OtpToken::where('identifier', (string) $user->id_akun)
            ->where('guard', 'ppdb_reset')
            ->where('sudah_digunakan', false)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'message' => 'Kode reset kata sandi telah dikirim.',
            'otp_code' => $otp ? $otp->kode : null,
        ]);
    }

    public function resetPasswordPpdb(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:akun_pendaftar,email',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = AkunPendaftar::where('email', $request->email)->firstOrFail();

        $otpService = app(\App\Support\OtpService::class);
        $valid = $otpService->verify((string) $user->id_akun, 'ppdb_reset', $request->otp);

        if (!$valid) {
            return response()->json([
                'message' => 'Kode OTP tidak valid atau sudah kadaluarsa.',
            ], 422);
        }

        $user->update([
            'password_hash' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui. Silakan login kembali.',
        ]);
    }
}