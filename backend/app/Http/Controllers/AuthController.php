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

        $this->ensurePpdbAdministrasiTagihan($pendaftar);
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

    public function tesStatusPpdb(Request $request)
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

        $pendaftar = $akun->pendaftaran()
            ->orderByDesc('id_pendaftaran')
            ->first();

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

        if ($updates !== []) {
            $pendaftar->fill($updates)->save();
            $this->syncPpdbBerkasRecords($pendaftar, $updates);
        }

        if (array_key_exists('phone_ppdb', $validated) && !empty($validated['phone_ppdb'])) {
            $akun->update(['phone' => $validated['phone_ppdb']]);
        }

        $this->ensurePpdbAdministrasiTagihan($pendaftar);
        $pendaftar->load(['tes', 'verifikasi']);

        return response()->json([
            'message' => 'Form pendaftaran berhasil disimpan.',
            'data' => [
                'pendaftaran' => $pendaftar,
                'flow' => $this->buildPpdbFlowState($pendaftar),
            ],
        ]);
    }

    protected function ensurePpdbAdministrasiTagihan(PpdbPendaftar $pendaftar): void
    {
        if (!$pendaftar->id_pendaftaran) {
            return;
        }

        $exists = PembayaranSpp::query()
            ->where('id_pendaftaran', $pendaftar->id_pendaftaran)
            ->where('metode_bayar', 'administrasi')
            ->exists();

        if ($exists) {
            return;
        }

        PembayaranSpp::create([
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'id_santri' => $pendaftar->id_santri,
            'nominal_bayar' => 100000,
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

        return $storedPaths;
    }

    protected function syncPpdbBerkasRecords(PpdbPendaftar $pendaftar, array $payload): void
    {
        $jenisByField = [
            'file_akta_path' => 'akta',
            'file_kk_path' => 'kk',
            'file_surat_rekomendasi_path' => 'surat_rekomendasi',
            'surat_pernyataan_file_path' => 'surat_pernyataan',
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
            'id_santri' => $guard === 'santri' ? ($user->santri->id_santri ?? null) : null,
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
        $verifikasi = $pendaftar->relationLoaded('verifikasi') ? $pendaftar->verifikasi : $pendaftar->verifikasi()->first();

        $konfigurasiTes = $this->resolveTesKonfigurasiForPendaftar($pendaftar);
        $fiturSoalAktif = (bool) ($konfigurasiTes?->fitur_soal_aktif ?? false);
        $soalTes = trim((string) ($konfigurasiTes?->soal_tes ?? ''));
        $formSchema = is_array($konfigurasiTes?->form_schema) ? $konfigurasiTes->form_schema : [];

        $tesAvailable = $fiturSoalAktif && (
            $soalTes !== '' || (is_array($formSchema) && count($formSchema) > 0)
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

        $step = 'lengkapi-form';
        if ($showTesPage) {
            $step = 'tes';
        } elseif ($showPembayaranPpdb && !$isPaymentVerified) {
            $step = 'pembayaran-ppdb';
        } elseif ($showPembayaranPpdb && $isStatusDiterima && $isPaymentVerified) {
            $step = 'siap-menjadi-santri';
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
            'show_halaman_pembayaran_ppdb' => $showPembayaranPpdb,
            'pembayaran_ppdb' => $pembayaranPpdb,
            'soal_tes' => $soalTes,
            'form_schema' => $formSchema,
            'step' => $step,
            'status_verifikasi' => $pendaftar->status_verifikasi,
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
        $latestPayment = PembayaranSpp::query()
            ->with(['kwitansi'])
            ->where('id_pendaftaran', $pendaftar->id_pendaftaran)
            ->orderByDesc('id_pembayaran')
            ->first();

        if (!$latestPayment) {
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
            'id_pembayaran' => $latestPayment->id_pembayaran,
            'status' => $latestPayment->status,
            'nominal_bayar' => $latestPayment->nominal_bayar,
            'tanggal_bayar' => optional($latestPayment->tanggal_bayar)->format('Y-m-d H:i:s'),
            'tanggal_verifikasi' => optional($latestPayment->tanggal_verifikasi)->format('Y-m-d H:i:s'),
            'metode_bayar' => $latestPayment->metode_bayar,
            'kwitansi_tersedia' => (bool) $latestPayment->kwitansi,
            'kwitansi' => $latestPayment->kwitansi ? [
                'id_kwitansi' => $latestPayment->kwitansi->id_kwitansi,
                'file_path_pdf' => $latestPayment->kwitansi->file_path_pdf,
                'jumlah' => $latestPayment->kwitansi->jumlah,
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

        if ($guard === 'santri' && $user instanceof DataAkunSantri) {
            return response()->json([
                'user' => [
                    'id' => $user->getKey(),
                    'id_santri' => $user->santri->id_santri ?? null,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->alamat_email,
                    'nomor_induk' => $user->nomor_induk,
                    'nama_unit' => $user->nama_unit,
                    'nama_kelas' => $user->nama_kelas,
                    'tahun_ajaran' => $user->tahun_ajaran,
                ],
                'role' => $guard
            ]);
        }

        return response()->json([
            'user' => $user,
            'role' => $guard
        ]);
    }
}