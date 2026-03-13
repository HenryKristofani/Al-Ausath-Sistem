<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\PpdbBerkas;
use App\Models\PpdbNotifikasi;
use App\Models\PpdbPendaftar;
use App\Models\PpdbTes;
use App\Models\PpdbVerifikasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PpdbController extends Controller
{
    /**
     * List data pendaftar PPDB.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = PpdbPendaftar::query()
            ->with(['akun', 'berkas', 'tes', 'verifikasi', 'notifikasi'])
            ->when($request->filled('status_verifikasi'), fn ($q) => $q->where('status_verifikasi', $request->status_verifikasi))
            ->when($request->filled('jenjang'), fn ($q) => $q->where('jenjang', $request->jenjang))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_calon', 'like', "%{$keyword}%")
                        ->orWhere('no_pendaftaran', 'like', "%{$keyword}%")
                        ->orWhere('nomor_umi', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_pendaftaran');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data pendaftar PPDB baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_akun' => ['nullable', 'integer', 'exists:akun_pendaftar,id_akun'],
            'no_pendaftaran' => ['required', 'string', 'max:50', 'unique:ppdb_pendaftar,no_pendaftaran'],
            'no_pendaftaran_final' => ['nullable', 'string', 'max:50'],
            'nama_calon' => ['required', 'string', 'max:200'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nomor_umi' => ['nullable', 'string', 'max:50'],
            'asal_kota' => ['nullable', 'string', 'max:100'],
            'is_luar_kota' => ['nullable', 'boolean'],
            'status_verifikasi' => ['nullable', 'string', 'max:30'],
            'tanggal_daftar' => ['nullable', 'date'],
        ]);

        $data = PpdbPendaftar::create($validated);

        return response()->json([
            'message' => 'Data pendaftar PPDB berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail pendaftar PPDB.
     */
    public function show(int $id): JsonResponse
    {
        $data = PpdbPendaftar::with(['akun', 'berkas', 'tes', 'verifikasi', 'notifikasi'])
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data pendaftar PPDB.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'id_akun' => ['nullable', 'integer', 'exists:akun_pendaftar,id_akun'],
            'no_pendaftaran' => ['sometimes', 'string', 'max:50', 'unique:ppdb_pendaftar,no_pendaftaran,' . $pendaftar->id_pendaftaran . ',id_pendaftaran'],
            'no_pendaftaran_final' => ['nullable', 'string', 'max:50'],
            'nama_calon' => ['sometimes', 'string', 'max:200'],
            'jenjang' => ['nullable', 'string', 'max:20'],
            'nomor_umi' => ['nullable', 'string', 'max:50'],
            'asal_kota' => ['nullable', 'string', 'max:100'],
            'is_luar_kota' => ['nullable', 'boolean'],
            'status_verifikasi' => ['nullable', 'string', 'max:30'],
            'tanggal_daftar' => ['nullable', 'date'],
        ]);

        $pendaftar->update($validated);

        return response()->json([
            'message' => 'Data pendaftar PPDB berhasil diperbarui.',
            'data' => $pendaftar->fresh(['akun', 'berkas', 'tes', 'verifikasi', 'notifikasi']),
        ]);
    }

    /**
     * Hapus data pendaftar PPDB.
     */
    public function destroy(int $id): JsonResponse
    {
        $pendaftar = PpdbPendaftar::findOrFail($id);

        DB::transaction(function () use ($pendaftar) {
            PpdbNotifikasi::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();
            PpdbVerifikasi::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();
            PpdbTes::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();
            PpdbBerkas::where('id_pendaftaran', $pendaftar->id_pendaftaran)->delete();

            $pendaftar->delete();
        });

        return response()->json([
            'message' => 'Data pendaftar PPDB berhasil dihapus.',
        ]);
    }

    /**
     * Tambahkan berkas PPDB untuk pendaftar.
     */
    public function storeBerkas(Request $request, int $id): JsonResponse
    {
        PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'jenis_berkas' => ['required', 'string', 'max:80'],
            'file_path' => ['required', 'string'],
            'uploaded_at' => ['nullable', 'date'],
        ]);

        $berkas = PpdbBerkas::create([
            'id_pendaftaran' => $id,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Berkas PPDB berhasil ditambahkan.',
            'data' => $berkas,
        ], 201);
    }

    /**
     * Simpan atau perbarui hasil tes PPDB.
     */
    public function upsertTes(Request $request, int $id): JsonResponse
    {
        PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'nilai' => ['nullable', 'numeric'],
            'status_tes' => ['nullable', 'string', 'max:30'],
            'catatan' => ['nullable', 'string'],
        ]);

        $tes = PpdbTes::updateOrCreate(
            ['id_pendaftaran' => $id],
            $validated
        );

        return response()->json([
            'message' => 'Hasil tes PPDB berhasil disimpan.',
            'data' => $tes,
        ]);
    }

    /**
     * Simpan atau perbarui verifikasi PPDB.
     */
    public function upsertVerifikasi(Request $request, int $id): JsonResponse
    {
        PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'tanggal_verif' => ['nullable', 'date'],
            'hasil' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string'],
        ]);

        $verifikasi = PpdbVerifikasi::updateOrCreate(
            ['id_pendaftaran' => $id],
            $validated
        );

        return response()->json([
            'message' => 'Verifikasi PPDB berhasil disimpan.',
            'data' => $verifikasi,
        ]);
    }

    /**
     * Tambahkan notifikasi PPDB untuk pendaftar.
     */
    public function storeNotifikasi(Request $request, int $id): JsonResponse
    {
        PpdbPendaftar::findOrFail($id);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:20'],
            'konten' => ['required', 'string'],
            'sent_at' => ['nullable', 'date'],
            'status_kirim' => ['nullable', 'string', 'max:20'],
        ]);

        $notifikasi = PpdbNotifikasi::create([
            'id_pendaftaran' => $id,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Notifikasi PPDB berhasil dibuat.',
            'data' => $notifikasi,
        ], 201);
    }
}
