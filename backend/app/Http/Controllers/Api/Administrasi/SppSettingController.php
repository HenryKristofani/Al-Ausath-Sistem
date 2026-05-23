<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataKelas;
use App\Models\SppGolongan;
use App\Models\SppSetting;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\DataSantri;
use App\Support\SppBillingService;

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
        $validated = $request->validate([
            'nama'               => ['nullable', 'string', 'max:200'],
            'id_unit'            => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'id_golongan_spp'    => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
            'kode_kelas'         => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kelas'              => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'jenjang'            => ['required_without_all:id_golongan_spp,kode_kelas,kelas,nama', 'nullable', 'string', 'max:20'],
            'kategori_tagihan_id'=> ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah'             => ['nullable', 'numeric'],
            'nominal'            => ['nullable', 'numeric'],
            'periode'            => ['nullable', 'string', 'max:20'],
            'tahun_ajaran'       => ['nullable', 'string', 'max:20'],
            'aktif'              => ['nullable', 'boolean'],
            'keterangan'         => ['nullable', 'string'],
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
            'nama'               => ['nullable', 'string', 'max:200'],
            'id_unit'            => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'id_golongan_spp'    => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
            'kode_kelas'         => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kelas'              => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'jenjang'            => ['nullable', 'string', 'max:20'],
            'kategori_tagihan_id'=> ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah'             => ['nullable', 'numeric'],
            'nominal'            => ['nullable', 'numeric'],
            'periode'            => ['nullable', 'string', 'max:20'],
            'tahun_ajaran'       => ['nullable', 'string', 'max:20'],
            'aktif'              => ['nullable', 'boolean'],
            'keterangan'         => ['nullable', 'string'],
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
     * Provision SPP bills untuk santri aktif.
     * 
     * Endpoint ini memungkinkan admin untuk manually trigger provisioning bills
     * tanpa menunggu scheduled job. Berguna untuk immediate effect setelah setup SPP settings.
     * 
     * Query Parameters:
     * - id_santri: provision hanya untuk santri tertentu
     * - id_setting: provision hanya dari setting tertentu
     * - id_unit: provision untuk semua santri di unit
     * 
     * Response: statistik jumlah santri yang diproses
     */
    public function provisionBills(Request $request, SppBillingService $billingService): JsonResponse
    {
        $idSantri = $request->query('id_santri');
        $idSetting = $request->query('id_setting');
        $idUnit = $request->query('id_unit');

        // Scope 1: Specific santri
        if ($idSantri) {
            $santri = DataSantri::find($idSantri);
            if (!$santri) {
                return response()->json([
                    'message' => 'Santri tidak ditemukan.',
                ], 404);
            }
            
            $billingService->provisionBillingForActiveSantri($santri);
            
            return response()->json([
                'message' => 'Bills berhasil diprovision untuk santri.',
                'data' => [
                    'santri_processed' => 1,
                    'id_santri' => $idSantri,
                ],
            ]);
        }

        // Scope 2-4: Multiple santri based on filter
        $query = DataSantri::query()
            ->where('is_deleted', false)
            ->whereRaw('UPPER(status) = ?', ['AKTIF']);

        if ($idUnit) {
            $query->whereHas('kelas', fn ($q) => $q->where('id_unit', $idUnit));
        }

        $santriList = $query->get();
        $processedCount = 0;

        foreach ($santriList as $santri) {
            try {
                $billingService->provisionBillingForActiveSantri($santri);
                $processedCount++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to provision bills for santri {$santri->id_santri}: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => "Bills berhasil diprovision untuk {$processedCount} santri aktif.",
            'data' => [
                'santri_processed' => $processedCount,
                'total_active_santri' => $santriList->count(),
            ],
        ]);
    }

    /**
     * Generate monthly tagihan SPP for a date range (periode).
     */
    public function generateTagihanPeriode(Request $request, int $id): JsonResponse
    {
        $setting = SppSetting::findOrFail($id);

        $validated = $request->validate([
            'bulan_mulai'   => ['required', 'integer', 'min:1', 'max:12'],
            'tahun_mulai'   => ['required', 'integer'],
            'bulan_selesai' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun_selesai' => ['required', 'integer'],
        ]);

        $startYear = (int) $validated['tahun_mulai'];
        $startMonth = (int) $validated['bulan_mulai'];
        $endYear = (int) $validated['tahun_selesai'];
        $endMonth = (int) $validated['bulan_selesai'];

        $monthNames = [
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

        $startDate = new \DateTime("{$startYear}-{$startMonth}-01");
        $endDate = new \DateTime("{$endYear}-{$endMonth}-01");

        $interval = new \DateInterval('P1M');
        $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 month'));

        $selectedMonths = [];
        foreach ($period as $dt) {
            $mNum = (int) $dt->format('n');
            $selectedMonths[] = $monthNames[$mNum];
        }

        // Determine santri match query based on setting properties priority
        if (!empty($setting->id_santri)) {
            $santriQuery = DataSantri::query()->where('id_santri', $setting->id_santri);
        } elseif (!empty($setting->kode_kelas)) {
            $santriQuery = DataSantri::query()->where('kode_kelas', $setting->kode_kelas);
        } elseif (!empty($setting->id_unit)) {
            $santriQuery = DataSantri::query()->whereHas('kelas.unit', fn($q) => $q->where('id_unit', $setting->id_unit));
        } elseif (!empty($setting->jenjang)) {
            $jenjang = strtoupper(trim((string) $setting->jenjang));
            $santriQuery = DataSantri::query()->whereHas('kelas.unit', function ($q) use ($jenjang) {
                $q->whereRaw('UPPER(nama_unit) = ?', [$jenjang])
                  ->orWhereRaw('UPPER(kode_unit) = ?', [$jenjang]);
            });
        } elseif (!empty($setting->id_golongan_spp)) {
            $santriQuery = DataSantri::query()->where('id_golongan_spp', $setting->id_golongan_spp);
        } else {
            $santriQuery = DataSantri::query();
        }

        $santris = $santriQuery
            ->where('is_deleted', false)
            ->whereRaw('UPPER(status) = ?', ['AKTIF'])
            ->get();

        $createdCount = 0;
        $totalMonthsCount = count($selectedMonths);

        foreach ($santris as $santri) {
            foreach ($selectedMonths as $month) {
                $created = \App\Models\PembayaranSpp::firstOrCreate(
                    [
                        'id_santri' => $santri->id_santri,
                        'id_setting' => $setting->id_setting,
                        'id_pendaftaran' => null,
                        'bulan' => $month,
                    ],
                    [
                        'nominal_bayar' => (float) ($setting->jumlah ?? 0),
                        'tanggal_bayar' => null,
                        'metode_bayar' => null,
                        'status' => 'menunggu_pembayaran',
                    ]
                );

                if ($created->wasRecentlyCreated) {
                    $createdCount++;
                }
            }
        }

        return response()->json([
            'message' => 'Tagihan SPP berhasil digenerate.',
            'data' => [
                'jumlah_tagihan_baru' => $createdCount,
                'jumlah_santri' => $santris->count(),
                'jumlah_periode' => $totalMonthsCount,
                'periode' => $selectedMonths,
            ],
        ]);
    }

    private function hydrateSettingPayload(array $validated): array
    {
        // Sinkronkan kelas → kode_kelas
        if (!array_key_exists('kode_kelas', $validated) && array_key_exists('kelas', $validated)) {
            $validated['kode_kelas'] = $validated['kelas'];
        }
        unset($validated['kelas']);

        // Sinkronkan nominal → jumlah (frontend mengirim 'nominal', DB menyimpan sebagai 'jumlah')
        if (!empty($validated['nominal']) && !isset($validated['jumlah'])) {
            $validated['jumlah'] = $validated['nominal'];
        }
        unset($validated['nominal']);

        // Sinkronkan tahun_ajaran → periode
        if (!empty($validated['tahun_ajaran']) && !isset($validated['periode'])) {
            $validated['periode'] = $validated['tahun_ajaran'];
        }
        unset($validated['tahun_ajaran']);

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
}
