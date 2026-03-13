<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataSantri;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DataSantriController extends Controller
{
    /**
     * List data santri.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataSantri::query()
            ->with(['kelas', 'akun'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('kode_kelas'), fn ($q) => $q->where('kode_kelas', $request->kode_kelas))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_santri');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data santri baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'unique:data_santri,nomor_induk'],
            'nama_lengkap_santri' => ['required', 'string', 'max:200'],
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'status' => ['nullable', 'string', 'max:20'],
            'tahun_masuk' => ['nullable', 'integer'],
            'tahun_lulus' => ['nullable', 'integer'],
            'jenis_kelamin' => ['nullable', 'string', 'size:1'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'berat_badan' => ['nullable', 'numeric'],
            'tinggi_badan' => ['nullable', 'numeric'],
            'gol_darah' => ['nullable', 'string', 'max:5'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kota_kabupaten' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'alamat_tinggal' => ['nullable', 'string'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_email' => ['nullable', 'email', 'max:100'],
            'nama_ayah_kandung' => ['nullable', 'string', 'max:200'],
            'nama_ibu_kandung' => ['nullable', 'string', 'max:200'],
            'nama_wali' => ['nullable', 'string', 'max:200'],
        ]);

        $data = DataSantri::create($validated);

        return response()->json([
            'message' => 'Data santri berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data santri.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataSantri::with(['kelas', 'akun'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data santri.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $santri = DataSantri::findOrFail($id);

        $validated = $request->validate([
            'nomor_induk' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('data_santri', 'nomor_induk')->ignore($santri->id_santri, 'id_santri'),
            ],
            'nama_lengkap_santri' => ['sometimes', 'string', 'max:200'],
            'kode_kelas' => ['sometimes', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'status' => ['nullable', 'string', 'max:20'],
            'tahun_masuk' => ['nullable', 'integer'],
            'tahun_lulus' => ['nullable', 'integer'],
            'jenis_kelamin' => ['nullable', 'string', 'size:1'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'berat_badan' => ['nullable', 'numeric'],
            'tinggi_badan' => ['nullable', 'numeric'],
            'gol_darah' => ['nullable', 'string', 'max:5'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kota_kabupaten' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'alamat_tinggal' => ['nullable', 'string'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'alamat_email' => ['nullable', 'email', 'max:100'],
            'nama_ayah_kandung' => ['nullable', 'string', 'max:200'],
            'nama_ibu_kandung' => ['nullable', 'string', 'max:200'],
            'nama_wali' => ['nullable', 'string', 'max:200'],
        ]);

        $santri->update($validated);

        return response()->json([
            'message' => 'Data santri berhasil diperbarui.',
            'data' => $santri->fresh(['kelas', 'akun']),
        ]);
    }

    /**
     * Hapus data santri.
     */
    public function destroy(int $id): JsonResponse
    {
        $santri = DataSantri::findOrFail($id);

        try {
            $santri->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data santri tidak dapat dihapus karena masih dipakai pada data pembayaran SPP atau data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data santri berhasil dihapus.',
        ]);
    }
}
