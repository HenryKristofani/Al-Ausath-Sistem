<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataAkunSantri;
use App\Models\DataSantri;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataSantriController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List data santri.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataSantri::query()
            ->with(['kelas', 'akun'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('kode_kelas'), fn($q) => $q->where('kode_kelas', $request->kode_kelas))
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
     * Opsi ringan data santri untuk autocomplete.
     */
    public function options(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $limit = max(1, min((int) $request->query('limit', 20), 50));

        $options = DataSantri::query()
            ->select(['id_santri', 'nomor_induk', 'nama_lengkap_santri'])
            ->when($keyword !== '', function ($q) use ($keyword) {
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('nama_lengkap_santri', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nama_lengkap_santri')
            ->limit($limit)
            ->get();

        return response()->json($options);
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

    /**
     * Buatkan akun untuk 1 santri dari master data santri.
     */
    public function buatAkun(int $id, Request $request): JsonResponse
    {
        $santri = DataSantri::with(['kelas', 'akun'])->findOrFail($id);

        if ($santri->akun) {
            return response()->json([
                'message' => 'Santri ini sudah memiliki akun.',
            ], 422);
        }

        $validated = $request->validate([
            'nama_akun' => ['nullable', 'string', 'max:100', 'unique:data_akun_santri,nama_akun'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $namaAkun = $validated['nama_akun'] ?? $santri->nomor_induk;

        $akun = DataAkunSantri::create([
            'nomor_induk' => $santri->nomor_induk,
            'nama_akun' => $namaAkun,
            'nama_lengkap' => $santri->nama_lengkap_santri,
            'nama_unit' => $santri->kelas?->kode_unit,
            'nama_kelas' => $santri->kelas?->nama_kelas,
            'tahun_ajaran' => $santri->kelas?->tahun_ajaran,
            'alamat_email' => $santri->alamat_email,
            'nomor_telepon' => $santri->nomor_telepon,
            'password_hash' => Hash::make($validated['password']),
            'status' => strtoupper($validated['status'] ?? 'AKTIF'),
        ]);

        return response()->json([
            'message' => 'Akun santri berhasil dibuat.',
            'data' => $akun,
        ], 201);
    }

    /**
     * Pindah kelas massal santri.
     */
    public function pindahKelas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:data_santri,id_santri'],
            'kode_kelas' => ['required', 'string', 'max:10', 'exists:data_kelas,kode_kelas'],
        ]);

        $updated = 0;

        DB::transaction(function () use ($validated, &$updated) {
            $updated = DataSantri::whereIn('id_santri', $validated['ids'])
                ->update(['kode_kelas' => $validated['kode_kelas']]);
        });

        return response()->json([
            'message' => 'Pindah kelas massal berhasil diproses.',
            'data' => [
                'total_terupdate' => $updated,
                'kode_kelas_baru' => $validated['kode_kelas'],
            ],
        ]);
    }

    /**
     * Import data santri dari CSV (upsert berdasarkan nomor_induk).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json([
                'message' => 'File CSV tidak dapat dibaca.',
            ], 422);
        }

        $headers = fgetcsv($handle);

        if ($headers === false) {
            fclose($handle);

            return response()->json([
                'message' => 'Header CSV tidak ditemukan.',
            ], 422);
        }

        $normalizedHeaders = array_map([$this, 'normalizeImportHeader'], $headers);

        $inserted = 0;
        $updated = 0;
        $failed = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $rowData = $this->combineRowData($normalizedHeaders, $row);
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $payload = $this->mapSantriPayload($rowData);

            $validator = Validator::make($payload, [
                'nomor_induk' => ['required', 'string', 'max:20'],
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

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = DataSantri::where('nomor_induk', $payload['nomor_induk'])->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            DataSantri::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data santri selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data santri ke CSV sesuai filter.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataSantri::query()
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('kode_kelas'), fn($q) => $q->where('kode_kelas', $request->kode_kelas))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_santri');

        $headers = [
            'nomor_induk',
            'nama_lengkap_santri',
            'kode_kelas',
            'status',
            'tahun_masuk',
            'tahun_lulus',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'agama',
            'berat_badan',
            'tinggi_badan',
            'gol_darah',
            'provinsi',
            'kota_kabupaten',
            'kecamatan',
            'kelurahan',
            'alamat_tinggal',
            'nomor_telepon',
            'alamat_email',
            'nama_ayah_kandung',
            'nama_ibu_kandung',
            'nama_wali',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->nomor_induk,
                        $row->nama_lengkap_santri,
                        $row->kode_kelas,
                        $row->status,
                        $row->tahun_masuk,
                        $row->tahun_lulus,
                        $row->jenis_kelamin,
                        $row->tempat_lahir,
                        optional($row->tanggal_lahir)->format('Y-m-d'),
                        $row->agama,
                        $row->berat_badan,
                        $row->tinggi_badan,
                        $row->gol_darah,
                        $row->provinsi,
                        $row->kota_kabupaten,
                        $row->kecamatan,
                        $row->kelurahan,
                        $row->alamat_tinggal,
                        $row->nomor_telepon,
                        $row->alamat_email,
                        $row->nama_ayah_kandung,
                        $row->nama_ibu_kandung,
                        $row->nama_wali,
                    ]);
                }
            });

            fclose($output);
        }, 'data-santri-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Template CSV import data santri.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'nomor_induk',
            'nama_lengkap_santri',
            'kode_kelas',
            'status',
            'tahun_masuk',
            'tahun_lulus',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'agama',
            'berat_badan',
            'tinggi_badan',
            'gol_darah',
            'provinsi',
            'kota_kabupaten',
            'kecamatan',
            'kelurahan',
            'alamat_tinggal',
            'nomor_telepon',
            'alamat_email',
            'nama_ayah_kandung',
            'nama_ibu_kandung',
            'nama_wali',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-santri.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function normalizeImportHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace([' ', '-', '/'], '_', $normalized);

        return preg_replace('/[^a-z0-9_]/', '', $normalized) ?? '';
    }

    private function combineRowData(array $headers, array $row): array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $data[$header] = $value === '' ? null : $value;
        }

        return $data;
    }

    private function isEmptyRow(array $rowData): bool
    {
        foreach ($rowData as $value) {
            if ($value !== null) {
                return false;
            }
        }

        return true;
    }

    private function mapSantriPayload(array $rowData): array
    {
        return [
            'nomor_induk' => $rowData['nomor_induk'] ?? null,
            'nama_lengkap_santri' => $rowData['nama_lengkap_santri'] ?? null,
            'kode_kelas' => $rowData['kode_kelas'] ?? null,
            'status' => $rowData['status'] ?? null,
            'tahun_masuk' => $rowData['tahun_masuk'] ?? null,
            'tahun_lulus' => $rowData['tahun_lulus'] ?? null,
            'jenis_kelamin' => $rowData['jenis_kelamin'] ?? null,
            'tempat_lahir' => $rowData['tempat_lahir'] ?? null,
            'tanggal_lahir' => $rowData['tanggal_lahir'] ?? null,
            'agama' => $rowData['agama'] ?? null,
            'berat_badan' => $rowData['berat_badan'] ?? null,
            'tinggi_badan' => $rowData['tinggi_badan'] ?? null,
            'gol_darah' => $rowData['gol_darah'] ?? null,
            'provinsi' => $rowData['provinsi'] ?? null,
            'kota_kabupaten' => $rowData['kota_kabupaten'] ?? null,
            'kecamatan' => $rowData['kecamatan'] ?? null,
            'kelurahan' => $rowData['kelurahan'] ?? null,
            'alamat_tinggal' => $rowData['alamat_tinggal'] ?? null,
            'nomor_telepon' => $rowData['nomor_telepon'] ?? null,
            'alamat_email' => $rowData['alamat_email'] ?? null,
            'nama_ayah_kandung' => $rowData['nama_ayah_kandung'] ?? null,
            'nama_ibu_kandung' => $rowData['nama_ibu_kandung'] ?? null,
            'nama_wali' => $rowData['nama_wali'] ?? null,
        ];
    }
}
