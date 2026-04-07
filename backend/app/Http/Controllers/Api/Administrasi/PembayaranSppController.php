<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\PembayaranSpp;
use App\Models\SppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PembayaranSppController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List pembayaran SPP.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = PembayaranSpp::query()
            ->with(['santri', 'setting', 'rekening'])
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
            'id_santri' => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'id_setting' => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nominal_bayar' => ['nullable', 'numeric'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

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

        $validated['status'] = $validated['status'] ?? 'tercatat';
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
            'id_santri' => ['nullable', 'integer', 'exists:data_santri,id_santri'],
            'id_setting' => ['nullable', 'integer', 'exists:spp_setting,id_setting'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nominal_bayar' => ['nullable', 'numeric'],
            'tanggal_bayar' => ['nullable', 'date'],
            'metode_bayar' => ['nullable', 'string', 'max:50'],
            'id_rekening' => ['nullable', 'integer', 'exists:data_rekening_bank,id_rekening'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

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
            'data' => $pembayaran->fresh(['santri', 'setting', 'rekening']),
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

        if ($idSantri) {
            $settingKhusus = SppSetting::query()
                ->where('id_santri', $idSantri)
                ->orderByDesc('id_setting')
                ->first();

            if ($settingKhusus) {
                return $settingKhusus;
            }
        }

        if ($jenjang) {
            return SppSetting::query()
                ->whereNull('id_santri')
                ->where('jenjang', $jenjang)
                ->orderByDesc('id_setting')
                ->first();
        }

        return null;
    }

    private function tunggakanStatuses(): array
    {
        return ['tunggakan', 'belum_lunas', 'pending', 'TUNGGAKAN', 'BELUM_LUNAS', 'PENDING'];
    }
}
