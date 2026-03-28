<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\DataKonversiNilai;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KonversiNilaiController extends Controller
{
    /**
     * List konversi nilai.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataKonversiNilai::query()
            ->when($request->filled('kode_unit'), fn($q) => $q->where('kode_unit', $request->kode_unit))
            ->when($request->filled('status'), fn($q) => $q->where('status', strtoupper((string) $request->status)))
            ->orderByDesc('id_konversi');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan konversi nilai.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'nilai_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'nilai_max' => ['required', 'numeric', 'min:0', 'max:100', 'gte:nilai_min'],
            'nilai_huruf' => ['required', 'string', 'max:5'],
            'predikat' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:AKTIF,NONAKTIF'],
        ]);

        $validated['status'] = strtoupper((string) ($validated['status'] ?? 'AKTIF'));

        $data = DataKonversiNilai::create($validated);

        return response()->json([
            'message' => 'Konversi nilai berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Detail konversi nilai.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataKonversiNilai::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui konversi nilai.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $konversi = DataKonversiNilai::findOrFail($id);

        $validated = $request->validate([
            'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'nilai_min' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_max' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_huruf' => ['sometimes', 'string', 'max:5'],
            'predikat' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'in:AKTIF,NONAKTIF'],
        ]);

        $nilaiMin = array_key_exists('nilai_min', $validated) ? (float) $validated['nilai_min'] : (float) $konversi->nilai_min;
        $nilaiMax = array_key_exists('nilai_max', $validated) ? (float) $validated['nilai_max'] : (float) $konversi->nilai_max;

        if ($nilaiMax < $nilaiMin) {
            return response()->json([
                'message' => 'nilai_max harus lebih besar atau sama dengan nilai_min.',
            ], 422);
        }

        if (array_key_exists('status', $validated)) {
            $validated['status'] = strtoupper((string) $validated['status']);
        }

        $konversi->update($validated);

        return response()->json([
            'message' => 'Konversi nilai berhasil diperbarui.',
            'data' => $konversi->fresh(),
        ]);
    }

    /**
     * Hapus konversi nilai.
     */
    public function destroy(int $id): JsonResponse
    {
        $konversi = DataKonversiNilai::findOrFail($id);
        $konversi->delete();

        return response()->json([
            'message' => 'Konversi nilai berhasil dihapus.',
        ]);
    }
}
