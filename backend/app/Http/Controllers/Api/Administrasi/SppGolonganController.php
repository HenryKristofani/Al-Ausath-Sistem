<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\SppGolongan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SppGolonganController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List golongan SPP per jenjang.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);

        $query = SppGolongan::query()
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', strtoupper((string) $request->jenjang)))
            ->when($request->filled('is_aktif'), fn ($q) => $q->where('is_aktif', $request->boolean('is_aktif')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = (string) $request->query('q');

                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_golongan', 'like', "%{$keyword}%")
                        ->orWhere('jenjang', 'like', "%{$keyword}%")
                        ->orWhere('keterangan', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('jenjang')
            ->orderBy('nama_golongan');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan golongan SPP baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_golongan' => ['required', 'string', 'max:100'],
            'jenjang' => ['required', 'string', 'max:20'],
            'nominal' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        $validated['jenjang'] = strtoupper(trim((string) $validated['jenjang']));
        $validated['is_aktif'] = $validated['is_aktif'] ?? true;

        $golongan = SppGolongan::create($validated);

        return response()->json([
            'message' => 'Golongan SPP berhasil dibuat.',
            'data' => $golongan,
        ], 201);
    }

    /**
     * Detail golongan SPP.
     */
    public function show(int $id): JsonResponse
    {
        $data = SppGolongan::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui golongan SPP.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $golongan = SppGolongan::findOrFail($id);

        $validated = $request->validate([
            'nama_golongan' => ['sometimes', 'string', 'max:100'],
            'jenjang' => ['sometimes', 'string', 'max:20'],
            'nominal' => ['sometimes', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string'],
            'is_aktif' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('jenjang', $validated)) {
            $validated['jenjang'] = strtoupper(trim((string) $validated['jenjang']));
        }

        $golongan->update($validated);

        return response()->json([
            'message' => 'Golongan SPP berhasil diperbarui.',
            'data' => $golongan->fresh(),
        ]);
    }

    /**
     * Hapus golongan SPP.
     */
    public function destroy(int $id): JsonResponse
    {
        $golongan = SppGolongan::findOrFail($id);
        $golongan->delete();

        return response()->json([
            'message' => 'Golongan SPP berhasil dihapus.',
        ]);
    }
}
