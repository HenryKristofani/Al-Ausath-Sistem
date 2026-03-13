<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\SppSetting;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SppSettingController extends Controller
{
    /**
     * List setting SPP.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = SppSetting::query()
            ->with(['unit', 'kategoriTagihan'])
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', $request->jenjang))
            ->when($request->filled('id_unit'), fn ($q) => $q->where('id_unit', $request->id_unit))
            ->orderByDesc('id_setting');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan setting SPP baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_unit' => ['nullable', 'integer', 'exists:data_unit,id_unit'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'kategori_tagihan_id' => ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah' => ['nullable', 'numeric'],
            'periode' => ['nullable', 'string', 'max:20'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $data = SppSetting::create($validated);

        return response()->json([
            'message' => 'Setting SPP berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Detail setting SPP.
     */
    public function show(int $id): JsonResponse
    {
        $data = SppSetting::with(['unit', 'kategoriTagihan', 'pembayaran'])->findOrFail($id);

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
            'jenjang' => ['nullable', 'string', 'max:20'],
            'kategori_tagihan_id' => ['nullable', 'integer', 'exists:data_kategori_tagihan,id_kategori'],
            'jumlah' => ['nullable', 'numeric'],
            'periode' => ['nullable', 'string', 'max:20'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $setting->update($validated);

        return response()->json([
            'message' => 'Setting SPP berhasil diperbarui.',
            'data' => $setting,
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
}
