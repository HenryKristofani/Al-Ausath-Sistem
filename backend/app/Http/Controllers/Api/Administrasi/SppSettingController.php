<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataKelas;
use App\Models\DataSantri;
use App\Models\SppGolongan;
use App\Models\SppSetting;
use App\Support\SppBillingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SppSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List setting SPP.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = SppSetting::query()
            ->with(['unit', 'kelas', 'kategoriTagihan', 'golonganSpp'])
            ->when($request->filled('jenjang'), fn ($q) => $q->whereRaw('UPPER(jenjang) = ?', [strtoupper((string) $request->jenjang)]))
            ->when($request->filled('id_unit'), fn ($q) => $q->where('id_unit', $request->id_unit))
            ->when($request->filled('id_golongan_spp'), fn ($q) => $q->where('id_golongan_spp', $request->id_golongan_spp))
            ->when($request->filled('kode_kelas'), fn ($q) => $q->where('kode_kelas', strtoupper((string) $request->kode_kelas)))
            ->orderByDesc('id_setting');

        return response()->json($query->paginate($perPage));
    }

    /**
     * List kelas aktif untuk referensi setting SPP.
     */
    public function kelasIndex(Request $request): JsonResponse
    {
        $query = DataKelas::query()
            ->select(['id_kelas', 'kode_unit', 'kode_kelas', 'nama_kelas', 'tahun_ajaran', 'status'])
            ->whereRaw('UPPER(status) = ?', ['AKTIF'])
            ->when($request->filled('kode_unit'), fn ($q) => $q->where('kode_unit', strtoupper((string) $request->kode_unit)))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', (string) $request->tahun_ajaran))
            ->orderBy('kode_unit')
            ->orderBy('nama_kelas');

        if (Schema::hasColumn('data_kelas', 'is_deleted')) {
            $query->where('is_deleted', false);
        }

        $rows = $query->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Simpan setting SPP baru.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->filled('id_unit') && !$request->filled('id_golongan_spp') && !$request->filled('kode_kelas') && !$request->filled('kelas') && !$request->filled('jenjang')) {
            return response()->json([
                'message' => 'Salah satu lingkup target (id_unit, id_golongan_spp, kode_kelas/kelas, jenjang) wajib diisi.',
                'errors' => [
                    'id_unit' => ['Salah satu lingkup target wajib diisi.'],
                ],
            ], 422);
        }

        $validated = $request->validate([
            'id_unit' => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'id_golongan_spp' => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
            'kode_kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'kategori_tagihan_id' => ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah' => ['nullable', 'numeric'],
            'periode' => ['nullable', 'string', 'max:20'],
            'keterangan' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $payload = $this->hydrateSettingPayload($validated);
        $data = SppSetting::create($payload);

        return response()->json([
            'message' => 'Setting SPP berhasil dibuat.',
            'data' => $data->load(['unit', 'kelas', 'kategoriTagihan', 'golonganSpp']),
        ], 201);
    }

    /**
     * Detail setting SPP.
     */
    public function show(int $id): JsonResponse
    {
        $data = SppSetting::with(['unit', 'kelas', 'kategoriTagihan', 'pembayaran', 'golonganSpp'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui setting SPP.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $setting = SppSetting::findOrFail($id);

        $validated = $request->validate([
            'id_unit' => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'id_golongan_spp' => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
            'kode_kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'kategori_tagihan_id' => ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah' => ['nullable', 'numeric'],
            'periode' => ['nullable', 'string', 'max:20'],
            'keterangan' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $payload = $this->hydrateSettingPayload($validated);
        $setting->update($payload);

        return response()->json([
            'message' => 'Setting SPP berhasil diperbarui.',
            'data' => $setting->fresh(['unit', 'kelas', 'kategoriTagihan', 'golonganSpp']),
        ]);
    }

    /**
     * Hapus setting SPP.
     */
    public function destroy(int $id): JsonResponse
    {
        $setting = SppSetting::findOrFail($id);

        try {
            $setting->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Setting SPP tidak dapat dihapus karena masih dipakai pada data pembayaran SPP.',
            ], 422);
        }

        return response()->json([
            'message' => 'Setting SPP berhasil dihapus.',
        ]);
    }

    /**
     * Provision tagihan SPP untuk santri aktif berdasarkan setting yang tersedia.
     *
     * POIN 12 — OPTIMASI: Gunakan chunk() agar tidak load semua santri ke memori
     * sekaligus, dan reset static year cache antar-chunk agar tidak stale.
     */
    public function provisionBills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_santri'      => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'kode_kelas'     => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'id_unit'        => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'id_golongan_spp' => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
            'jenjang'        => ['nullable', 'string', 'max:20'],
        ]);

        $query = DataSantri::query()->with(['kelas.unit']);

        if (Schema::hasColumn('data_santri', 'is_deleted')) {
            $query->where(function ($subQuery) {
                $subQuery->whereNull('is_deleted')->orWhere('is_deleted', false);
            });
        }

        $query->whereRaw("UPPER(COALESCE(status, '')) = ?", ['AKTIF']);

        if (!empty($validated['id_santri'])) {
            $query->where('id_santri', $validated['id_santri']);
        }

        if (!empty($validated['kode_kelas'])) {
            $query->where('kode_kelas', strtoupper(trim((string) $validated['kode_kelas'])));
        }

        if (!empty($validated['id_golongan_spp'])) {
            $query->where('id_golongan_spp', $validated['id_golongan_spp']);
        }

        if (!empty($validated['id_unit'])) {
            $query->whereHas('kelas.unit', function ($unitQuery) use ($validated) {
                $unitQuery->where('id_unit', $validated['id_unit']);
            });
        }

        if (!empty($validated['jenjang'])) {
            $jenjang = strtoupper(trim((string) $validated['jenjang']));
            $query->whereHas('kelas.unit', function ($unitQuery) use ($jenjang) {
                $unitQuery->whereRaw("UPPER(COALESCE(kode_unit, nama_unit, '')) = ?", [$jenjang]);
            });
        }

        $service = app(SppBillingService::class);
        $processed = 0;

        // OPTIMASI: chunk 100 santri per batch — hemat memori & DB connection
        $query->orderBy('id_santri')->chunk(100, function ($batch) use ($service, &$processed) {
            foreach ($batch as $santri) {
                /** @var DataSantri $santri */
                $service->provisionBillingForActiveSantri($santri);
                $processed++;
            }
            // Reset static year cache setiap chunk agar tidak stale
            SppBillingService::resetCache();
        });

        return response()->json([
            'message' => $processed > 0
                ? "Provision tagihan SPP berhasil: {$processed} santri diproses."
                : 'Tidak ada santri aktif yang cocok untuk diproses.',
            'data' => [
                'processed' => $processed,
                'filters'   => [
                    'id_santri'      => $validated['id_santri'] ?? null,
                    'kode_kelas'     => $validated['kode_kelas'] ?? null,
                    'id_unit'        => $validated['id_unit'] ?? null,
                    'id_golongan_spp' => $validated['id_golongan_spp'] ?? null,
                    'jenjang'        => $validated['jenjang'] ?? null,
                ],
            ],
        ]);
    }

    /**
     * Generate tagihan SPP per periode untuk setting tertentu.
     *
     * POIN 12 — OPTIMASI: Diganti dari N×M firstOrCreate individual
     * menjadi satu SELECT untuk cek existing + satu bulk INSERT.
     * Dari O(N×M) query → O(2) query, jauh lebih cepat untuk ratusan santri.
     */
    public function generateTagihanPeriode(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'bulan_mulai' => ['required', 'integer', 'between:1,12'],
            'tahun_mulai' => ['required', 'integer', 'min:1900'],
            'bulan_selesai' => ['required', 'integer', 'between:1,12'],
            'tahun_selesai' => ['required', 'integer', 'min:1900'],
        ]);

        if (
            (int) $validated['tahun_selesai'] < (int) $validated['tahun_mulai']
            || (
                (int) $validated['tahun_selesai'] === (int) $validated['tahun_mulai']
                && (int) $validated['bulan_selesai'] < (int) $validated['bulan_mulai']
            )
        ) {
            return response()->json([
                'message' => 'Periode selesai harus sama dengan atau setelah periode mulai.',
            ], 422);
        }

        $setting = SppSetting::with(['kategoriTagihan', 'golonganSpp', 'unit', 'kelas.unit'])->findOrFail($id);

        // ── Bangun query santri ───────────────────────────────────────────────
        $santriQuery = DataSantri::query()->with(['kelas.unit']);

        if (Schema::hasColumn('data_santri', 'is_deleted')) {
            $santriQuery->where(function ($subQuery) {
                $subQuery->whereNull('is_deleted')->orWhere('is_deleted', false);
            });
        }

        $santriQuery->whereRaw("UPPER(COALESCE(status, '')) = ?", ['AKTIF']);

        // Bangun filter secara AND (bukan OR) agar hanya santri yang cocok
        // dengan SEMUA kriteria setting yang akan ditagih.
        if (!empty($setting->id_santri)) {
            $santriQuery->where('id_santri', $setting->id_santri);
        }

        if (!empty($setting->kode_kelas)) {
            $santriQuery->where('kode_kelas', strtoupper(trim((string) $setting->kode_kelas)));
        }

        if (!empty($setting->id_unit)) {
            $santriQuery->whereHas('kelas.unit', fn ($u) => $u->where('id_unit', $setting->id_unit));
        }

        if (!empty($setting->jenjang)) {
            $jenjang = strtoupper(trim((string) $setting->jenjang));
            $santriQuery->whereHas('kelas.unit', fn ($u) => $u->whereRaw(
                "UPPER(TRIM(COALESCE(kode_unit, ''))) = ?",
                [$jenjang]
            ));
        }

        if (!empty($setting->id_golongan_spp)) {
            $santriQuery->where('id_golongan_spp', $setting->id_golongan_spp);
        }

        // Hanya ambil kolom yang dibutuhkan — tidak perlu SELECT *
        $santriList = $santriQuery
            ->select(['id_santri', 'is_anak_guru'])
            ->orderBy('id_santri')
            ->get();

        $periods = $this->buildPeriodLabels(
            (int) $validated['bulan_mulai'],
            (int) $validated['tahun_mulai'],
            (int) $validated['bulan_selesai'],
            (int) $validated['tahun_selesai'],
        );

        if ($santriList->isEmpty() || empty($periods)) {
            return response()->json([
                'message' => 'Tidak ada santri aktif atau periode yang cocok.',
                'data' => ['jumlah_tagihan_baru' => 0, 'jumlah_santri' => 0, 'jumlah_periode' => count($periods), 'periode' => $periods],
            ]);
        }

        $santriIds = $santriList->pluck('id_santri')->all();

        // ── OPTIMASI: Satu query untuk ambil semua tagihan existing ──────────
        $existingSet = \App\Models\PembayaranSpp::query()
            ->whereIn('id_santri', $santriIds)
            ->where('id_setting', $setting->id_setting)
            ->whereNull('id_pendaftaran')
            ->whereIn('bulan', $periods)
            ->select(['id_santri', 'bulan'])
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->id_santri}::{$row->bulan}" => true])
            ->all();

        // ── Bangun batch rows yang belum ada ─────────────────────────────────
        $idSettingVal = $setting->id_setting;

        // Map is_anak_guru per santri untuk nominal kalkulasi
        $isAnakGuruMap = $santriList->pluck('is_anak_guru', 'id_santri')->all();
        $baseNominal = (float) ($setting->jumlah ?? 0);

        $rows = [];
        foreach ($santriIds as $idSantri) {
            $nominal = $isAnakGuruMap[$idSantri] ? $baseNominal * 0.5 : $baseNominal;
            foreach ($periods as $period) {
                if (isset($existingSet["{$idSantri}::{$period}"])) {
                    continue; // Sudah ada, skip
                }
                $rows[] = [
                    'id_santri'       => $idSantri,
                    'id_setting'      => $idSettingVal,
                    'id_pendaftaran'  => null,
                    'bulan'           => $period,
                    'nominal_bayar'   => $nominal,
                    'tanggal_bayar'   => null,
                    'metode_bayar'    => null,
                    'status'          => 'menunggu_pembayaran',
                ];
            }
        }

        $createdCount = 0;

        if (!empty($rows)) {
            // ── OPTIMASI: Satu bulk INSERT mengganti ratusan firstOrCreate ───
            // Chunk 500 baris per INSERT untuk menghindari batas parameter DB
            foreach (array_chunk($rows, 500) as $chunk) {
                \Illuminate\Support\Facades\DB::table('pembayaran_spp')->insert($chunk);
                $createdCount += count($chunk);
            }
        }

        return response()->json([
            'message' => $createdCount > 0
                ? "Generate tagihan berhasil: {$createdCount} tagihan baru dibuat."
                : 'Tidak ada tagihan baru yang dibuat (semua sudah ada).',
            'data' => [
                'jumlah_tagihan_baru' => $createdCount,
                'jumlah_santri'       => $santriList->count(),
                'jumlah_periode'      => count($periods),
                'periode'             => $periods,
            ],
        ]);
    }

    private function hydrateSettingPayload(array $validated): array
    {
        if (!array_key_exists('kode_kelas', $validated) && array_key_exists('kelas', $validated)) {
            $validated['kode_kelas'] = $validated['kelas'];
        }

        unset($validated['kelas']);

        if (array_key_exists('kode_kelas', $validated) && $validated['kode_kelas'] !== null) {
            $validated['kode_kelas'] = strtoupper(trim((string) $validated['kode_kelas']));
        }

        if (!empty($validated['kode_kelas']) && empty($validated['jenjang'])) {
            $kelas = DataKelas::query()
                ->select(['kode_unit'])
                ->where('kode_kelas', $validated['kode_kelas'])
                ->first();

            if ($kelas) {
                $validated['jenjang'] = strtoupper(trim((string) $kelas->kode_unit));
            }
        }

        if (!empty($validated['id_golongan_spp']) && empty($validated['jenjang'])) {
            $golongan = SppGolongan::find($validated['id_golongan_spp']);
            if ($golongan) {
                $validated['jenjang'] = strtoupper(trim((string) $golongan->jenjang));
            }
        }

        if (!empty($validated['jenjang'])) {
            $validated['jenjang'] = strtoupper(trim((string) $validated['jenjang']));
        }

        return $validated;
    }

    /**
     * Build daftar label periode dari bulan/tahun mulai sampai selesai.
     *
     * @return array<int, string>
     */
    private function buildPeriodLabels(int $bulanMulai, int $tahunMulai, int $bulanSelesai, int $tahunSelesai): array
    {
        $bulanNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $periods = [];
        $currentMonth = $bulanMulai;
        $currentYear = $tahunMulai;

        while ($currentYear < $tahunSelesai || ($currentYear === $tahunSelesai && $currentMonth <= $bulanSelesai)) {
            $periods[] = $bulanNames[$currentMonth] . ' ' . $currentYear;

            $currentMonth++;
            if ($currentMonth > 12) {
                $currentMonth = 1;
                $currentYear++;
            }
        }

        return $periods;
    }
}
