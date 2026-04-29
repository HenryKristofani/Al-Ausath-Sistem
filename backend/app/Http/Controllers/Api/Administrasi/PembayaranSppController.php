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
            ->with(['santri', 'setting', 'rekening', 'pendaftarPpdb', 'kwitansi'])
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
        $data = PembayaranSpp::with(['santri', 'setting', 'rekening'])->findOrFail($id);

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
            'data' => $pembayaran->fresh(['santri', 'setting', 'rekening', 'pendaftarPpdb', 'kwitansi']),
        ]);
    }

    /**
     * Verifikasi pembayaran SPP (alur website bill + admin verifikasi).
     */
    public function verifikasiPembayaran(Request $request, int $id): JsonResponse
    {
        $pembayaran = PembayaranSpp::with(['pendaftarPpdb.akun', 'santri.kelas', 'kwitansi'])->findOrFail($id);

        if ($request->has('status')) {
            $request->merge([
                'status' => $this->normalizeVerifikasiStatusInput((string) $request->input('status')),
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:menunggu_pembayaran,menunggu_konfirmasi,dibatalkan,lunas,menunggu_verifikasi,terverifikasi,ditolak'],
            'id_petugas_verifikator' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tanggal_verifikasi' => ['nullable', 'date'],
        ]);

        $result = DB::transaction(function () use ($pembayaran, $validated) {
            $tanggalVerifikasi = isset($validated['tanggal_verifikasi'])
                ? Carbon::parse($validated['tanggal_verifikasi'])
                : now();

            $statusStorage = $this->mapStatusToStorage((string) $validated['status']);

            $pembayaran->update([
                'status' => $statusStorage,
                'id_petugas_verifikator' => $validated['id_petugas_verifikator'] ?? $pembayaran->id_petugas_verifikator,
                'tanggal_verifikasi' => $tanggalVerifikasi,
            ]);

            $integrasi = null;
            $kwitansi = $pembayaran->kwitansi;

            if ($statusStorage === 'terverifikasi') {
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
            }

            return [
                'pembayaran' => $pembayaran->fresh(['santri', 'setting', 'rekening', 'pendaftarPpdb', 'kwitansi']),
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
     * Endpoint status verifikasi pembayaran untuk halaman verifikasi pembayaran.
     */
    public function updateStatusVerifikasi(Request $request, int $id): JsonResponse
    {
        return $this->verifikasiPembayaran($request, $id);
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
        return [
            'tunggakan',
            'belum_lunas',
            'pending',
            'menunggu_pembayaran',
            'menunggu_verifikasi',
            'TUNGGAKAN',
            'BELUM_LUNAS',
            'PENDING',
            'MENUNGGU_PEMBAYARAN',
            'MENUNGGU_VERIFIKASI',
        ];
    }

    private function normalizeVerifikasiStatusInput(string $status): string
    {
        $normalized = mb_strtolower(trim($status));

        return match ($normalized) {
            'pending' => 'menunggu_pembayaran',
            'menunggu_verifikasi' => 'menunggu_konfirmasi',
            'terverifikasi' => 'lunas',
            'ditolak' => 'dibatalkan',
            default => $normalized,
        };
    }

    private function mapStatusToStorage(string $status): string
    {
        $normalized = mb_strtolower(trim($status));

        return match ($normalized) {
            'menunggu_pembayaran' => 'menunggu_pembayaran',
            'menunggu_konfirmasi', 'menunggu_verifikasi' => 'menunggu_verifikasi',
            'lunas', 'terverifikasi' => 'terverifikasi',
            'dibatalkan', 'ditolak' => 'ditolak',
            default => $normalized,
        };
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
}
