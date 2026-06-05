<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
use App\Models\DataKelas;
use App\Models\DataSantri;
use App\Models\KwitansiPdf;
use App\Models\PembayaranSpp;
use App\Models\PpdbPendaftar;
use App\Models\SppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Support\PpdbRegistrationNumberService;
use App\Support\KwitansiPdfGenerator;

class PembayaranSppController extends Controller
{
    private PpdbRegistrationNumberService $nomorService;

    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->nomorService = app(PpdbRegistrationNumberService::class);
    }

    /**
     * List pembayaran SPP.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = PembayaranSpp::query()
            ->with(['santri', 'setting', 'pendaftarPpdb', 'kwitansi'])
            ->when($request->filled('id_pendaftaran'), fn ($q) => $q->where('id_pendaftaran', $request->id_pendaftaran))
            ->when($request->filled('id_santri'), fn ($q) => $q->where('id_santri', $request->id_santri))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->boolean('tunggakan_only'), fn ($q) => $q->whereIn('status', $this->tunggakanStatuses()))
            ->when($request->filled('tanggal_mulai'), fn ($q) => $q->whereDate('tanggal_bayar', '>=', $request->tanggal_mulai))
            ->when($request->filled('tanggal_selesai'), fn ($q) => $q->whereDate('tanggal_bayar', '<=', $request->tanggal_selesai))
            ->orderByDesc('id_pembayaran');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan pembayaran SPP.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_pendaftaran' => ['nullable', 'integer', 'exists:ppdb_pendaftar,id_pendaftaran'],
            'id_santri' => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'id_setting' => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nominal_bayar' => ['nullable', 'numeric'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        if (empty($validated['id_santri']) && empty($validated['id_pendaftaran'])) {
            return response()->json([
                'message' => 'Gunakan id_santri atau id_pendaftaran saat membuat tagihan SPP.',
            ], 422);
        }

        if (!empty($validated['id_pendaftaran'])) {
            $pendaftar = PpdbPendaftar::find($validated['id_pendaftaran']);
            if ($pendaftar && empty($validated['id_santri']) && !empty($pendaftar->id_santri)) {
                $validated['id_santri'] = $pendaftar->id_santri;
            }
            if ($pendaftar && empty($validated['jenjang'])) {
                $validated['jenjang'] = $pendaftar->jenjang;
            }
        }

        $setting = $this->resolveSetting(
            $validated['id_setting'] ?? null,
            $validated['id_santri'] ?? null,
            $validated['jenjang'] ?? null
        );

        if (empty($validated['id_setting']) && $setting) {
            $validated['id_setting'] = $setting->id_setting;
        }

        if (!array_key_exists('nominal_bayar', $validated) && $setting) {
            $validated['nominal_bayar'] = $setting->jumlah;
        }

        $validated['status'] = $validated['status'] ?? 'menunggu_verifikasi';
        unset($validated['jenjang']);

        $data = PembayaranSpp::create($validated);

        return response()->json([
            'message' => 'Pembayaran SPP berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Detail pembayaran SPP.
     */
    public function show(int $id): JsonResponse
    {
        $data = PembayaranSpp::with(['santri', 'setting'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui pembayaran SPP.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);

        $validated = $request->validate([
            'id_pendaftaran' => ['nullable', 'integer', 'exists:ppdb_pendaftar,id_pendaftaran'],
            'id_santri' => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'id_setting' => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nominal_bayar' => ['nullable', 'numeric'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
            'status' => ['nullable', 'string', 'max:30'],
            'tanggal_verifikasi' => ['nullable', 'date'],
            'id_petugas_verifikator' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
        ]);

        if (!empty($validated['id_pendaftaran'])) {
            $pendaftar = PpdbPendaftar::find($validated['id_pendaftaran']);
            if ($pendaftar && empty($validated['id_santri']) && !empty($pendaftar->id_santri)) {
                $validated['id_santri'] = $pendaftar->id_santri;
            }
            if ($pendaftar && empty($validated['jenjang'])) {
                $validated['jenjang'] = $pendaftar->jenjang;
            }
        }

        $setting = $this->resolveSetting(
            $validated['id_setting'] ?? $pembayaran->id_setting,
            $validated['id_santri'] ?? $pembayaran->id_santri,
            $validated['jenjang'] ?? null
        );

        if (empty($validated['id_setting']) && $setting) {
            $validated['id_setting'] = $setting->id_setting;
        }

        if (!array_key_exists('nominal_bayar', $validated) && $setting) {
            $validated['nominal_bayar'] = $setting->jumlah;
        }

        unset($validated['jenjang']);

        $pembayaran->update($validated);

        return response()->json([
            'message' => 'Pembayaran SPP berhasil diperbarui.',
            'data' => $pembayaran->fresh(['santri', 'setting', 'pendaftarPpdb', 'kwitansi']),
        ]);
    }

    /**
     * Verifikasi pembayaran SPP (alur website bill + admin verifikasi).
     */
    public function verifikasiPembayaran(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::with(['pendaftarPpdb.akun', 'santri.kelas', 'kwitansi'])->findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'in:menunggu_verifikasi,terverifikasi,ditolak'],
            'id_petugas_verifikator' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tanggal_verifikasi' => ['nullable', 'date'],
        ]);

        $result = DB::transaction(function () use ($pembayaran, $validated) {
            $tanggalVerifikasi = isset($validated['tanggal_verifikasi'])
                ? Carbon::parse($validated['tanggal_verifikasi'])
                : now();

            $pembayaran->update([
                'status' => $validated['status'],
                'id_petugas_verifikator' => $validated['id_petugas_verifikator'] ?? $pembayaran->id_petugas_verifikator,
                'tanggal_verifikasi' => $tanggalVerifikasi,
            ]);

            $integrasi = null;
            $kwitansi = $pembayaran->kwitansi;

            if ($validated['status'] === 'terverifikasi') {
                $integrasi = $this->integrasikanPembayaranTerverifikasi($pembayaran);

                $kwitansi = KwitansiPdf::firstOrCreate(
                    ['id_pembayaran' => $pembayaran->id_pembayaran],
                    [
                        'id_petugas' => $validated['id_petugas_verifikator'] ?? null,
                        'jenis' => 'spp',
                        'jumlah' => $pembayaran->nominal_bayar,
                        'file_path_pdf' => 'kwitansi/spp/' . $pembayaran->id_pembayaran . '/kwitansi-' . $pembayaran->id_pembayaran . '.pdf',
                    ]
                );

                $this->ensureKwitansiPdfFile($pembayaran, $kwitansi);
            }

            return [
                'pembayaran' => $pembayaran->fresh(['santri', 'setting', 'pendaftarPpdb', 'kwitansi']),
                'kwitansi' => $kwitansi,
                'integrasi_ppdb' => $integrasi,
            ];
        });

        return response()->json([
            'message' => 'Verifikasi pembayaran SPP berhasil disimpan.',
            'data' => $result,
        ]);
    }

    /**
     * Ringkasan tunggakan SPP per santri.
     */
    public function tunggakanRingkasan(Request $request): JsonResponse
    {
        $statusTunggakan = $this->tunggakanStatuses();

        $rows = PembayaranSpp::query()
            ->with(['santri.kelas', 'setting.kategoriTagihan'])
            ->whereIn('status', $statusTunggakan)
            ->when($request->filled('id_santri'), fn ($q) => $q->where('id_santri', $request->id_santri))
            ->orderByDesc('tanggal_bayar')
            ->get();

        $data = $rows
            ->groupBy('id_santri')
            ->map(function ($items) {
                $first = $items->first();
                $santri = $first?->santri;

                return [
                    'id_santri' => $santri?->id_santri,
                    'nomor_induk' => $santri?->nomor_induk,
                    'nama_santri' => $santri?->nama_lengkap_santri,
                    'kode_kelas' => $santri?->kode_kelas,
                    'jumlah_transaksi_tunggakan' => $items->count(),
                    'total_tunggakan' => (float) $items->sum('nominal_bayar'),
                    'rincian' => $items->map(fn ($row) => [
                        'id_pembayaran' => $row->id_pembayaran,
                        'id_setting' => $row->id_setting,
                        'nominal_bayar' => $row->nominal_bayar,
                        'tanggal_bayar' => $row->tanggal_bayar,
                        'status' => $row->status,
                        'kategori' => $row->setting?->kategoriTagihan?->nama_tagihan,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'status_tunggakan' => $statusTunggakan,
                'catatan' => 'Tunggakan tetap tercatat berdasarkan id_santri, sehingga tidak hilang saat santri naik kelas.',
            ],
        ]);
    }

    /**
     * Hapus pembayaran SPP.
     */
    public function destroy(int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);
        $pembayaran->delete();

        return response()->json([
            'message' => 'Pembayaran SPP berhasil dihapus.',
        ]);
    }

    private function resolveSetting(?int $idSetting, ?int $idSantri, ?string $jenjang): ?SppSetting
    {
        if ($idSetting) {
            return SppSetting::find($idSetting);
        }

        $santri = null;

        if ($idSantri) {
            $santri = DataSantri::with(['kelas'])->find($idSantri);

            $settingKhusus = SppSetting::query()
                ->where('id_santri', $idSantri)
                ->orderByDesc('id_setting')
                ->first();

            if ($settingKhusus) {
                return $settingKhusus;
            }

            $kodeKelas = strtoupper(trim((string) ($santri?->kode_kelas ?? '')));
            if ($kodeKelas !== '') {
                $settingKelas = SppSetting::query()
                    ->whereNull('id_santri')
                    ->where('kode_kelas', $kodeKelas)
                    ->orderByDesc('id_setting')
                    ->first();

                if ($settingKelas) {
                    return $settingKelas;
                }
            }
        }

        $jenjangTarget = $this->normalizeJenjangTarget($jenjang);
        if (!$jenjangTarget && $santri) {
            $jenjangTarget = $this->resolveJenjangFromSantri($santri);
        }

        if ($jenjangTarget) {
            return SppSetting::query()
                ->whereNull('id_santri')
                ->whereRaw('UPPER(jenjang) = ?', [strtoupper($jenjangTarget)])
                ->orderByDesc('id_setting')
                ->first();
        }

        return null;
    }

    private function resolveJenjangFromSantri(DataSantri $santri): ?string
    {
        $fromUnit = (string) ($santri->kelas?->kode_unit ?? '');
        return $this->normalizeJenjangTarget($fromUnit);
    }

    private function normalizeJenjangTarget(?string $jenjang): ?string
    {
        $trimmed = strtoupper(trim((string) $jenjang));
        return $trimmed !== '' ? $trimmed : null;
    }

    private function tunggakanStatuses(): array
    {
        return ['tunggakan', 'belum_lunas', 'pending', 'TUNGGAKAN', 'BELUM_LUNAS', 'PENDING'];
    }

    private function integrasikanPembayaranTerverifikasi(PembayaranSpp $pembayaran): ?array
    {
        $pendaftar = null;

        if ($pembayaran->id_pendaftaran) {
            $pendaftar = PpdbPendaftar::with('akun')
                ->find($pembayaran->id_pendaftaran);
        }

        if (!$pendaftar) {
            return null;
        }

        $statusDiterima = in_array(
            mb_strtolower(trim((string) $pendaftar->status_verifikasi)),
            ['diterima', 'lulus', 'accepted'],
            true
        );

        if (!$statusDiterima) {
            return [
                'message' => 'Pendaftar belum berstatus diterima, data santri belum dibuat.',
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
            ];
        }

        if (!$pendaftar->kode_kelas_diterima) {
            return [
                'message' => 'Kode kelas diterima belum diisi, data santri belum dibuat.',
                'id_pendaftaran' => $pendaftar->id_pendaftaran,
            ];
        }

        $kelas = DataKelas::where('kode_kelas', $pendaftar->kode_kelas_diterima)->firstOrFail();
        $nomorInduk = $pendaftar->nomor_induk_generated
            ?: $this->nomorService->generateNomorIndukAfterPayment($pendaftar, (int) now()->format('Y'));

        $santri = DataSantri::firstOrNew(['nomor_induk' => $nomorInduk]);
        $santri->fill([
            'nama_lengkap_santri' => $pendaftar->nama_calon,
            'kode_kelas' => $pendaftar->kode_kelas_diterima,
            'status' => 'AKTIF',
            'tahun_masuk' => $santri->tahun_masuk ?: (int) now()->format('Y'),
            'jenis_kelamin' => $pendaftar->jenis_kelamin,
            'tempat_lahir' => $pendaftar->tempat_lahir,
            'tanggal_lahir' => $pendaftar->tanggal_lahir,
            'alamat_tinggal' => $pendaftar->alamat_lengkap,
            'nomor_telepon' => $pendaftar->no_hp_calon ?: $pendaftar->akun?->phone,
            'alamat_email' => $pendaftar->akun?->email,
            'nama_ayah_kandung' => $pendaftar->nama_ayah,
            'nama_ibu_kandung' => $pendaftar->nama_ibu,
        ]);
        $santri->save();

        $passwordHash = $pendaftar->akun?->password_hash;
        $passwordDefault = null;

        if (!$passwordHash) {
            $passwordDefault = $nomorInduk;
            $passwordHash = Hash::make($passwordDefault);
        }

        $akunSantri = DataAkunSantri::updateOrCreate(
            ['nomor_induk' => $nomorInduk],
            [
                'nama_akun' => $nomorInduk,
                'nama_lengkap' => $pendaftar->nama_calon,
                'nama_unit' => $kelas->kode_unit,
                'nama_kelas' => $kelas->nama_kelas,
                'tahun_ajaran' => $kelas->tahun_ajaran,
                'alamat_email' => $pendaftar->akun?->email,
                'nomor_telepon' => $pendaftar->no_hp_calon ?: $pendaftar->akun?->phone,
                'password_hash' => $passwordHash,
                'status' => 'AKTIF',
            ]
        );

        $pendaftar->update([
            'id_santri' => $santri->id_santri,
            'nomor_induk_generated' => $nomorInduk,
            'tanggal_diterima' => $pendaftar->tanggal_diterima ?: now()->toDateString(),
        ]);

        if ((int) ($pembayaran->id_santri ?? 0) !== (int) $santri->id_santri) {
            $pembayaran->update(['id_santri' => $santri->id_santri]);
        }

        return [
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'id_santri' => $santri->id_santri,
            'nomor_induk' => $nomorInduk,
            'akun_santri' => [
                'id_akun_santri' => $akunSantri->id_akun_santri,
                'nama_akun' => $akunSantri->nama_akun,
                'password_default' => $passwordDefault,
            ],
        ];
    }

    /**
     * Update status verifikasi pembayaran (untuk unified endpoint PUT /api/administrasi/pembayaran/{id}/status).
     */
    public function updateStatusVerifikasi(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $statusMapped = match (strtolower(trim($validated['status']))) {
            'lunas', 'terverifikasi' => 'terverifikasi',
            'dibatalkan', 'ditolak', 'batal' => 'ditolak',
            'menunggu_konfirmasi', 'menunggu_verifikasi' => 'menunggu_verifikasi',
            'menunggu_pembayaran', 'pending' => 'menunggu_pembayaran',
            default => $validated['status'],
        };

        $result = DB::transaction(function () use ($pembayaran, $statusMapped, $validated) {
            $user = auth()->user();
            $idPetugas = ($user instanceof \App\Models\DataPetugas) ? $user->id_petugas : null;
            
            $pembayaran->update([
                'status' => $statusMapped,
                'id_petugas_verifikator' => $idPetugas ?? $pembayaran->id_petugas_verifikator,
                'tanggal_verifikasi' => now(),
                'catatan_bayar' => $validated['keterangan'] ?? $pembayaran->catatan_bayar,
            ]);

            $integrasi = null;
            $kwitansi = $pembayaran->kwitansi;

            if ($statusMapped === 'terverifikasi') {
                $integrasi = $this->integrasikanPembayaranTerverifikasi($pembayaran);

                $kwitansi = KwitansiPdf::firstOrCreate(
                    ['id_pembayaran' => $pembayaran->id_pembayaran],
                    [
                        'id_petugas' => $idPetugas ?? null,
                        'jenis' => 'spp',
                        'jumlah' => $pembayaran->nominal_bayar,
                        'file_path_pdf' => 'kwitansi/spp/' . $pembayaran->id_pembayaran . '/kwitansi-' . $pembayaran->id_pembayaran . '.pdf',
                    ]
                );

                $this->ensureKwitansiPdfFile($pembayaran, $kwitansi);
            }

            return [
                'pembayaran' => $pembayaran->fresh(['santri', 'setting', 'pendaftarPpdb', 'kwitansi']),
                'kwitansi' => $kwitansi,
                'integrasi_ppdb' => $integrasi,
            ];
        });

        // Map status for frontend
        $statusFE = match ($result['pembayaran']->status) {
            'menunggu_verifikasi' => 'menunggu_konfirmasi',
            'terverifikasi' => 'lunas',
            'ditolak' => 'dibatalkan',
            default => $result['pembayaran']->status,
        };

        $statusLabel = match ($statusFE) {
            'menunggu_pembayaran' => 'Menunggu Pembayaran',
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'dibatalkan' => 'Dibatalkan',
            'lunas' => 'Lunas',
            default => ucfirst(str_replace('_', ' ', $result['pembayaran']->status)),
        };

        return response()->json([
            'message' => 'Status pembayaran berhasil diperbarui.',
            'data' => [
                'id_pembayaran' => $result['pembayaran']->id_pembayaran,
                'status' => $statusFE,
                'status_label' => $statusLabel,
                'pembayaran' => $result['pembayaran'],
                'integrasi_ppdb' => $result['integrasi_ppdb'],
            ]
        ]);
    }

    /**
     * Download PDF Kwitansi Pembayaran SPP/PPDB.
     */
    public function downloadKwitansi(int $id): mixed
    {
        $pembayaran = PembayaranSpp::with(['santri.kelas.unit', 'pendaftarPpdb.akun', 'setting', 'kwitansi'])->findOrFail($id);

        $kwitansi = $pembayaran->kwitansi;

        if (!$kwitansi) {
            $user = auth()->user();
            $idPetugas = ($user instanceof \App\Models\DataPetugas) ? $user->id_petugas : null;
            
            $kwitansi = KwitansiPdf::create([
                'id_pembayaran' => $pembayaran->id_pembayaran,
                'id_petugas' => $idPetugas ?? $pembayaran->id_petugas_verifikator ?? null,
                'jenis' => 'spp',
                'jumlah' => $pembayaran->nominal_bayar,
                'file_path_pdf' => 'kwitansi/spp/' . $pembayaran->id_pembayaran . '/kwitansi-' . $pembayaran->id_pembayaran . '.pdf',
            ]);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (!$disk->exists((string) $kwitansi->file_path_pdf)) {
            $this->ensureKwitansiPdfFile($pembayaran, $kwitansi);
        }

        return response()->download($disk->path((string) $kwitansi->file_path_pdf));
    }

    /**
     * Helper to generate/ensure receipt PDF exists on disk.
     */
    private function ensureKwitansiPdfFile(PembayaranSpp $pembayaran, KwitansiPdf $kwitansi): string
    {
        $santri = $pembayaran->santri;
        $pendaftar = $pembayaran->pendaftarPpdb;

        $nama = $santri?->nama_lengkap_santri ?? $pendaftar?->nama_calon ?? '-';
        $nomorInduk = $santri?->nomor_induk
            ?? $pendaftar?->nomor_induk_generated
            ?? $pendaftar?->no_pendaftaran_final
            ?? $pendaftar?->no_pendaftaran
            ?? '-';

        $kelas = $santri?->kelas?->nama_kelas ?? $pendaftar?->kode_kelas_diterima ?? '-';
        $unit = $santri?->kelas?->unit?->nama_unit
            ?? $santri?->kelas?->kode_unit
            ?? strtoupper((string) ($pendaftar?->jenjang ?: $pendaftar?->program_pendaftaran ?: '-'));

        $namaPetugas = 'Petugas Keuangan';
        if ($kwitansi->id_petugas) {
            $petugas = \App\Models\DataPetugas::find($kwitansi->id_petugas);
            if ($petugas) {
                $namaPetugas = $petugas->nama_lengkap;
            }
        }

        // Build rincian & periode
        $isPpdb = !empty($pembayaran->id_pendaftaran);
        $setting = $pembayaran->setting;
        
        $rincian = $pembayaran->bulan 
            ? (($setting?->kategoriTagihan?->nama_tagihan ?: $setting?->keterangan ?: 'SPP') . ' - ' . $pembayaran->bulan) 
            : ($setting?->kategoriTagihan?->nama_tagihan ?: $setting?->keterangan ?: ($isPpdb ? 'Tagihan PPDB' : 'Tagihan SPP'));
        
        if ($pembayaran->catatan_bayar) {
            $rincian .= ' (' . $pembayaran->catatan_bayar . ')';
        }

        $payload = [
            'title' => $isPpdb ? 'Kwitansi Pembayaran PPDB' : 'Kwitansi Pembayaran SPP',
            'jenis' => $isPpdb ? 'PPDB' : 'SPP',
            'nomor_kwitansi' => str_pad((string) $kwitansi->id_kwitansi, 5, '0', STR_PAD_LEFT),
            'nomor_invoice' => '#' . str_pad((string) $pembayaran->id_pembayaran, 8, '0', STR_PAD_LEFT),
            'tanggal' => optional($pembayaran->tanggal_bayar ?? $pembayaran->tanggal_verifikasi ?? now())->format('d/m/Y H:i'),
            'nama' => $nama,
            'nomor_induk' => $nomorInduk,
            'unit' => $unit,
            'kelas' => $kelas,
            'bulan' => $pembayaran->bulan ?? '',
            'periode' => $setting?->periode ?? '',
            'rincian' => $rincian,
            'metode' => $pembayaran->metode_bayar ?? 'Tunai',
            'status' => 'Lunas',
            'nominal' => 'Rp ' . number_format((float) ($pembayaran->nominal_bayar ?? 0), 0, ',', '.'),
            'nominal_raw' => (float) ($pembayaran->nominal_bayar ?? 0),
            'sisa_tagihan' => 'Rp 0',
            'nama_petugas' => $namaPetugas,
        ];

        return app(KwitansiPdfGenerator::class)->generate(
            (string) $kwitansi->file_path_pdf,
            $payload
        );
    }
}
