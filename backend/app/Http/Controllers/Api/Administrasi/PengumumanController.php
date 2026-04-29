<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengumumanController extends Controller
{
    /**
     * Public: daftar pengumuman aktif untuk landing page.
     * Dipanggil tanpa auth.
     */
    public function indexPublic(Request $request): JsonResponse
    {
        $query = Pengumuman::query()
            ->where('is_aktif', true)
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->kategori))
            ->when(
                $request->boolean('expired', false) === false,
                fn ($q) => $q->where(function ($sub) {
                    $sub->whereNull('tanggal_selesai')
                        ->orWhere('tanggal_selesai', '>=', now()->toDateString());
                })
            )
            ->orderByDesc('is_pinned')
            ->orderBy('urutan')
            ->orderByDesc('created_at');

        $limit = min((int) $request->query('limit', 20), 100);

        return response()->json([
            'data' => $query->limit($limit)->get(),
        ]);
    }

    /**
     * Admin: daftar semua pengumuman (butuh auth).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pengumuman::query()
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->kategori))
            ->when($request->filled('is_aktif'), fn ($q) => $q->where('is_aktif', filter_var($request->is_aktif, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('is_pinned')
            ->orderBy('urutan')
            ->orderByDesc('created_at');

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Admin: simpan pengumuman baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul'           => ['required', 'string', 'max:255'],
            'konten'          => ['required', 'string'],
            'kategori'        => ['nullable', 'string', 'max:50', 'in:umum,ppdb,akademik,kegiatan'],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'is_aktif'        => ['nullable', 'boolean'],
            'is_pinned'       => ['nullable', 'boolean'],
            'urutan'          => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['kategori'] = $validated['kategori'] ?? 'umum';

        $pengumuman = Pengumuman::create($validated);

        return response()->json([
            'message' => 'Pengumuman berhasil dibuat.',
            'data'    => $pengumuman,
        ], 201);
    }

    /**
     * Admin: detail satu pengumuman.
     */
    public function show(int $id): JsonResponse
    {
        $pengumuman = Pengumuman::findOrFail($id);

        return response()->json(['data' => $pengumuman]);
    }

    /**
     * Admin: perbarui pengumuman.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pengumuman = Pengumuman::findOrFail($id);

        $validated = $request->validate([
            'judul'           => ['sometimes', 'string', 'max:255'],
            'konten'          => ['sometimes', 'string'],
            'kategori'        => ['nullable', 'string', 'max:50', 'in:umum,ppdb,akademik,kegiatan'],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'is_aktif'        => ['nullable', 'boolean'],
            'is_pinned'       => ['nullable', 'boolean'],
            'urutan'          => ['nullable', 'integer', 'min:0'],
        ]);

        $pengumuman->update($validated);

        return response()->json([
            'message' => 'Pengumuman berhasil diperbarui.',
            'data'    => $pengumuman->fresh(),
        ]);
    }

    /**
     * Admin: hapus pengumuman.
     */
    public function destroy(int $id): JsonResponse
    {
        $pengumuman = Pengumuman::findOrFail($id);
        $pengumuman->delete();

        return response()->json([
            'message' => 'Pengumuman berhasil dihapus.',
        ]);
    }
}
