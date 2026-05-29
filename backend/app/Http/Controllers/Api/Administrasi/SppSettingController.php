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
            'id_unit' => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'id_golongan_spp' => ['nullable', 'integer', 'exists:spp_golongan,id_golongan'],
            'kode_kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'jenjang' => ['required_without_all:id_golongan_spp,kode_kelas,kelas', 'nullable', 'string', 'max:20'],
            'kategori_tagihan_id' => ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah' => ['nullable', 'numeric'],
            'periode' => ['nullable', 'string', 'max:20'],
            'keterangan' => ['nullable', 'string'],
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
}
