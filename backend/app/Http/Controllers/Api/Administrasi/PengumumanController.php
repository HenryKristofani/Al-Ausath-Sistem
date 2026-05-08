<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\Pengumuman;
use App\Models\PengumumanLampiran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PengumumanController extends Controller
{
    /**
     * Public: daftar pengumuman aktif untuk landing page.
     * Dipanggil tanpa auth.
     */
    public function indexPublic(Request $request): JsonResponse
    {
        $query = Pengumuman::query()
            ->with('lampirans')
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
            'data' => $query->limit($limit)->get()->map(fn (Pengumuman $item) => $this->serializePengumuman($item)),
        ]);
    }

    /**
     * Public: detail pengumuman aktif untuk landing page.
     */
    public function showPublic(int $id): JsonResponse
    {
        $pengumuman = Pengumuman::query()
            ->with('lampirans')
            ->whereKey($id)
            ->where('is_aktif', true)
            ->firstOrFail();

        if ($pengumuman->tanggal_selesai && now()->startOfDay()->gt(Carbon::parse((string) $pengumuman->tanggal_selesai))) {
            abort(404);
        }

        return response()->json(['data' => $this->serializePengumuman($pengumuman)]);
    }

    /**
     * Admin: daftar semua pengumuman (butuh auth).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pengumuman::query()
            ->with('lampirans')
            ->when($request->filled('kategori'), fn ($q) => $q->where('kategori', $request->kategori))
            ->when($request->filled('is_aktif'), fn ($q) => $q->where('is_aktif', filter_var($request->is_aktif, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('is_pinned')
            ->orderBy('urutan')
            ->orderByDesc('created_at');

        return response()->json([
            'data' => $query->get()->map(fn (Pengumuman $item) => $this->serializePengumuman($item)),
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
            'lampiran'        => ['nullable', 'file', 'max:10240'],
            'kategori'        => ['nullable', 'string', 'max:50', 'in:umum,ppdb,akademik,kegiatan'],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'is_aktif'        => ['nullable', 'boolean'],
            'is_pinned'       => ['nullable', 'boolean'],
            'urutan'          => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['kategori'] = $validated['kategori'] ?? 'umum';

        if ($request->hasFile('lampiran')) {
            $file = $request->file('lampiran');
            $validated['lampiran_path'] = $file->store('pengumuman/lampiran', 'public');
            $validated['lampiran_nama_asli'] = $file->getClientOriginalName();
            $validated['lampiran_mime'] = $file->getClientMimeType();
            $validated['lampiran_size'] = $file->getSize();
        }

        unset($validated['lampiran']);

        $pengumuman = Pengumuman::create($validated);

        if (isset($file) && $file instanceof UploadedFile) {
            PengumumanLampiran::create([
                'pengumuman_id' => $pengumuman->id,
                'path'          => $validated['lampiran_path'],
                'nama_asli'     => $validated['lampiran_nama_asli'],
                'mime'          => $validated['lampiran_mime'],
                'size'          => $validated['lampiran_size'],
            ]);
        }

        $pengumuman->load('lampirans');

        return response()->json([
            'message' => 'Pengumuman berhasil dibuat.',
            'data'    => $this->serializePengumuman($pengumuman),
        ], 201);
    }

    /**
     * Admin: detail satu pengumuman.
     */
    public function show(int $id): JsonResponse
    {
        $pengumuman = Pengumuman::with('lampirans')->findOrFail($id);

        return response()->json(['data' => $this->serializePengumuman($pengumuman)]);
    }

    /**
     * Admin: perbarui pengumuman.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pengumuman = Pengumuman::with('lampirans')->findOrFail($id);

        $validated = $request->validate([
            'judul'           => ['sometimes', 'string', 'max:255'],
            'konten'          => ['sometimes', 'string'],
            'lampiran'        => ['nullable', 'file', 'max:10240'],
            'hapus_lampiran'  => ['nullable', 'boolean'],
            'kategori'        => ['nullable', 'string', 'max:50', 'in:umum,ppdb,akademik,kegiatan'],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date'],
            'is_aktif'        => ['nullable', 'boolean'],
            'is_pinned'       => ['nullable', 'boolean'],
            'urutan'          => ['nullable', 'integer', 'min:0'],
        ]);

        $hapusLampiran = (bool) ($validated['hapus_lampiran'] ?? false);
        unset($validated['hapus_lampiran']);

        if ($request->hasFile('lampiran')) {
            $this->deleteAllLampiran($pengumuman);

            $file = $request->file('lampiran');
            $validated['lampiran_path'] = $file->store('pengumuman/lampiran', 'public');
            $validated['lampiran_nama_asli'] = $file->getClientOriginalName();
            $validated['lampiran_mime'] = $file->getClientMimeType();
            $validated['lampiran_size'] = $file->getSize();

            PengumumanLampiran::create([
                'pengumuman_id' => $pengumuman->id,
                'path'          => $validated['lampiran_path'],
                'nama_asli'     => $validated['lampiran_nama_asli'],
                'mime'          => $validated['lampiran_mime'],
                'size'          => $validated['lampiran_size'],
            ]);
        } elseif ($hapusLampiran) {
            $this->deleteAllLampiran($pengumuman);
            $validated['lampiran_path'] = null;
            $validated['lampiran_nama_asli'] = null;
            $validated['lampiran_mime'] = null;
            $validated['lampiran_size'] = null;
        }

        unset($validated['lampiran']);

        $pengumuman->update($validated);

        return response()->json([
            'message' => 'Pengumuman berhasil diperbarui.',
            'data'    => $this->serializePengumuman($pengumuman->fresh()),
        ]);
    }

    /**
     * Admin: hapus pengumuman.
     */
    public function destroy(int $id): JsonResponse
    {
        $pengumuman = Pengumuman::findOrFail($id);

        $this->deleteAllLampiran($pengumuman);

        $pengumuman->delete();

        return response()->json([
            'message' => 'Pengumuman berhasil dihapus.',
        ]);
    }

    private function deleteAllLampiran(Pengumuman $pengumuman): void
    {
        if ($pengumuman->lampiran_path) {
            Storage::disk('public')->delete($pengumuman->lampiran_path);
        }

        foreach ($pengumuman->lampirans as $lampiran) {
            Storage::disk('public')->delete($lampiran->path);
            $lampiran->delete();
        }
    }

    private function serializePengumuman(Pengumuman $pengumuman): array
    {
        $data = $pengumuman->toArray();
        $attachment = $pengumuman->latestLampiran();
        $path = $attachment?->path ?? $pengumuman->lampiran_path;

        $data['lampiran_url'] = $path
            ? Storage::url($path)
            : null;

        return $data;
    }
}
