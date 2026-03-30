<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
use App\Models\DataKelas;
use App\Models\DataSantri;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataAkunSantriController extends Controller
{
    /**
     * List data akun santri.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataAkunSantri::query()
            ->with(['santri.kelas'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('kode_kelas'), function ($q) use ($request) {
                $q->whereHas('santri', fn ($subQ) => $subQ->where('kode_kelas', $request->kode_kelas));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_akun', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_akun_santri');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data akun santri baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'max:20', 'exists:data_santri,nomor_induk', 'unique:data_akun_santri,nomor_induk'],
            'nama_akun' => ['nullable', 'string', 'max:100', 'unique:data_akun_santri,nama_akun'],
            'alamat_email' => ['nullable', 'email', 'max:100'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $santri = DataSantri::with('kelas')->where('nomor_induk', $validated['nomor_induk'])->firstOrFail();

        $namaAkun = $validated['nama_akun'] ?? $santri->nomor_induk;
        $namaAkun = $this->generateUniqueNamaAkun($namaAkun);

        $data = DataAkunSantri::create([
            'nomor_induk' => $santri->nomor_induk,
            'nama_akun' => $namaAkun,
            'nama_lengkap' => $santri->nama_lengkap_santri,
            'nama_unit' => $santri->kelas?->kode_unit,
            'nama_kelas' => $santri->kelas?->nama_kelas,
            'tahun_ajaran' => $santri->kelas?->tahun_ajaran,
            'alamat_email' => $validated['alamat_email'] ?? $santri->alamat_email,
            'nomor_telepon' => $validated['nomor_telepon'] ?? $santri->nomor_telepon,
            'password_hash' => Hash::make($validated['password']),
            'status' => strtoupper($validated['status'] ?? 'AKTIF'),
        ]);

        return response()->json([
            'message' => 'Data akun santri berhasil dibuat.',
            'data' => $data->fresh(['santri.kelas']),
        ], 201);
    }

    /**
     * Tampilkan detail data akun santri.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataAkunSantri::with(['santri.kelas'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data akun santri.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $akun = DataAkunSantri::findOrFail($id);

        $validated = $request->validate([
            'nama_akun' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('data_akun_santri', 'nama_akun')->ignore($akun->id_akun_santri, 'id_akun_santri'),
            ],
            'alamat_email' => ['nullable', 'email', 'max:100'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (!empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        unset($validated['password']);

        $akun->update($validated);

        return response()->json([
            'message' => 'Data akun santri berhasil diperbarui.',
            'data' => $akun->fresh(['santri.kelas']),
        ]);
    }

    /**
     * Hapus data akun santri.
     */
    public function destroy(int $id): JsonResponse
    {
        $akun = DataAkunSantri::findOrFail($id);
        $akun->delete();

        return response()->json([
            'message' => 'Data akun santri berhasil dihapus.',
        ]);
    }

    /**
     * Export data akun santri ke CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataAkunSantri::query()
            ->with('santri')
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('kode_kelas'), function ($q) use ($request) {
                $q->whereHas('santri', fn ($subQ) => $subQ->where('kode_kelas', $request->kode_kelas));
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_akun', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('nama_lengkap', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_akun_santri');

        $headers = [
            'nomor_induk',
            'nama_akun',
            'nama_lengkap',
            'nama_unit',
            'nama_kelas',
            'tahun_ajaran',
            'alamat_email',
            'nomor_telepon',
            'status',
            'last_login',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->nomor_induk,
                        $row->nama_akun,
                        $row->nama_lengkap,
                        $row->nama_unit,
                        $row->nama_kelas,
                        $row->tahun_ajaran,
                        $row->alamat_email,
                        $row->nomor_telepon,
                        $row->status,
                        optional($row->last_login)->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($output);
        }, 'data-akun-santri-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Sinkronkan akun santri yang belum memiliki akun.
     */
    public function sinkron(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['nullable', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
            'nomor_induk' => ['nullable', 'array'],
            'nomor_induk.*' => ['string', 'exists:data_santri,nomor_induk'],
            'default_password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $query = DataSantri::query()
            ->with('kelas')
            ->whereDoesntHave('akun')
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['nomor_induk']), fn ($q) => $q->whereIn('nomor_induk', $validated['nomor_induk']));

        $santriList = $query->get();

        $created = 0;

        foreach ($santriList as $santri) {
            $namaAkun = $this->generateUniqueNamaAkun($santri->nomor_induk);
            $passwordRaw = $validated['default_password'] ?? $santri->nomor_induk;

            DataAkunSantri::create([
                'nomor_induk' => $santri->nomor_induk,
                'nama_akun' => $namaAkun,
                'nama_lengkap' => $santri->nama_lengkap_santri,
                'nama_unit' => $santri->kelas?->kode_unit,
                'nama_kelas' => $santri->kelas?->nama_kelas,
                'tahun_ajaran' => $santri->kelas?->tahun_ajaran,
                'alamat_email' => $santri->alamat_email,
                'nomor_telepon' => $santri->nomor_telepon,
                'password_hash' => Hash::make($passwordRaw),
                'status' => strtoupper($validated['status'] ?? 'AKTIF'),
            ]);

            $created++;
        }

        return response()->json([
            'message' => 'Sinkron akun santri selesai diproses.',
            'data' => [
                'total_akun_dibuat' => $created,
            ],
        ]);
    }

    /**
     * Opsi kelas yang masih punya santri tanpa akun.
     */
    public function kelasTanpaAkun(): JsonResponse
    {
        $rows = DataKelas::query()
            ->select(['kode_kelas', 'nama_kelas', 'kode_unit', 'tahun_ajaran'])
            ->whereHas('santri', function ($q) {
                $q->whereDoesntHave('akun');
            })
            ->withCount([
                'santri as jumlah_santri_belum_akun' => function ($q) {
                    $q->whereDoesntHave('akun');
                },
            ])
            ->orderBy('kode_kelas')
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * Opsi santri tanpa akun berdasarkan kelas.
     */
    public function santriTanpaAkunByKelas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
        ]);

        $rows = DataSantri::query()
            ->where('kode_kelas', $validated['kode_kelas'])
            ->whereDoesntHave('akun')
            ->orderBy('nama_lengkap_santri')
            ->get(['id_santri', 'nomor_induk', 'nama_lengkap_santri', 'kode_kelas', 'alamat_email', 'nomor_telepon']);

        return response()->json(['data' => $rows]);
    }

    private function generateUniqueNamaAkun(string $baseName): string
    {
        $candidate = trim($baseName);
        $candidate = $candidate === '' ? 'santri' : $candidate;

        if (!DataAkunSantri::where('nama_akun', $candidate)->exists()) {
            return $candidate;
        }

        $counter = 1;

        while (DataAkunSantri::where('nama_akun', $candidate . '-' . $counter)->exists()) {
            $counter++;
        }

        return $candidate . '-' . $counter;
    }
}
