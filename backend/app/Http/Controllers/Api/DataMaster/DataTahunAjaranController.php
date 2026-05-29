<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataTahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTahunAjaranController extends Controller
{
    /**
     * List data tahun ajaran (exclude soft delete via flag is_deleted).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataTahunAjaran::query()
            ->withCount([
                'kelas as jumlah_kelas',
                'santri as jumlah_santri',
            ])
            ->where('is_deleted', false)
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_tahun', 'like', "%{$keyword}%")
                        ->orWhere('nama_tahun', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_tahun_ajaran');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data tahun ajaran baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_tahun' => [
                'required',
                'string',
                'max:20',
                Rule::unique('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'nama_tahun' => ['required', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated['kode_tahun'] = strtoupper($validated['kode_tahun']);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        $existingDeleted = DataTahunAjaran::where('kode_tahun', $validated['kode_tahun'])
            ->where('is_deleted', true)
            ->first();

        if ($existingDeleted) {
            $existingDeleted->update([
                'nama_tahun' => $validated['nama_tahun'],
                'keterangan' => $validated['keterangan'] ?? null,
                'status' => $validated['status'] ?? 'AKTIF',
                'is_deleted' => false,
                'deleted_at' => null,
            ]);

            $data = $existingDeleted->fresh();
        } else {
            $validated['is_deleted'] = false;
            $data = DataTahunAjaran::create($validated);
        }

        $data->loadCount([
            'kelas as jumlah_kelas',
            'santri as jumlah_santri',
        ]);

        return response()->json([
            'message' => 'Data tahun ajaran berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data tahun ajaran.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataTahunAjaran::query()
            ->withCount([
                'kelas as jumlah_kelas',
                'santri as jumlah_santri',
            ])
            ->where('is_deleted', false)
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data tahun ajaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tahun = DataTahunAjaran::where('is_deleted', false)->findOrFail($id);

        $validated = $request->validate([
            'kode_tahun' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('data_tahun_ajaran', 'kode_tahun')
                    ->ignore($tahun->id_tahun_ajaran, 'id_tahun_ajaran')
                    ->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'nama_tahun' => ['sometimes', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (array_key_exists('kode_tahun', $validated) && $validated['kode_tahun'] !== null) {
            $validated['kode_tahun'] = strtoupper($validated['kode_tahun']);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
            if ($validated['status'] === 'NONAKTIF') {
                $activeKelasIds = \App\Models\DataKelas::where('tahun_ajaran', $tahun->kode_tahun)
                    ->where('is_deleted', false)
                    ->where('status', 'AKTIF')
                    ->pluck('kode_kelas');

                if ($activeKelasIds->isNotEmpty()) {
                    // Cek apakah ada santri aktif di kelas-kelas tersebut
                    $hasActiveSantri = \App\Models\DataSantri::whereIn('kode_kelas', $activeKelasIds)
                        ->where('is_deleted', false)
                        ->where('status', 'AKTIF')
                        ->exists();

                    if ($hasActiveSantri) {
                        return response()->json([
                            'message' => 'Tidak dapat menonaktifkan tahun ajaran karena ada kelas yang masih memiliki santri aktif.',
                        ], 422);
                    }

                    // Jika tidak ada santri aktif, auto-nonaktifkan kelas-kelas tersebut
                    \App\Models\DataKelas::whereIn('kode_kelas', $activeKelasIds)
                        ->update(['status' => 'NONAKTIF']);

                    // Cascading ke Kelas Mapel dan Jadwal Pembelajaran
                    $kelasMapelIds = \App\Models\DataKelasMapel::whereIn('kode_kelas', $activeKelasIds)
                        ->pluck('id_kelas_mapel');

                    if ($kelasMapelIds->isNotEmpty()) {
                        \App\Models\DataKelasMapel::whereIn('id_kelas_mapel', $kelasMapelIds)
                            ->update(['status' => 'NONAKTIF']);

                        \App\Models\JadwalPembelajaran::whereIn('id_kelas_mapel', $kelasMapelIds)
                            ->update(['status' => 'NONAKTIF']);
                    }
                }
            }
        }

        $tahun->update($validated);
        $tahun->loadCount([
            'kelas as jumlah_kelas',
            'santri as jumlah_santri',
        ]);

        return response()->json([
            'message' => 'Data tahun ajaran berhasil diperbarui.',
            'data' => $tahun,
        ]);
    }

    /**
     * Hapus data tahun ajaran dari database (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $tahun = DataTahunAjaran::where('is_deleted', false)->findOrFail($id);

        $hasKelas = \App\Models\DataKelas::where('tahun_ajaran', $tahun->kode_tahun)
            ->where('is_deleted', false)
            ->exists();
            
        if ($hasKelas) {
            return response()->json([
                'message' => 'Tidak dapat menghapus tahun ajaran karena masih digunakan oleh data kelas.',
            ], 422);
        }

        $tahun->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Data tahun ajaran berhasil dihapus.',
        ]);
    }

    /**
     * Import data tahun ajaran dari CSV (upsert berdasarkan kode_tahun).
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
        $affectedKodeTahun = [];

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $rowData = $this->combineRowData($normalizedHeaders, $row);
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $payload = $this->mapPayload($rowData);

            $validator = Validator::make($payload, [
                'kode_tahun' => ['required', 'string', 'max:20'],
                'nama_tahun' => ['required', 'string', 'max:50'],
                'keterangan' => ['nullable', 'string'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $payload['kode_tahun'] = strtoupper($payload['kode_tahun']);
            $payload['status'] = strtoupper($payload['status'] ?? 'AKTIF');
            $payload['is_deleted'] = false;
            $payload['deleted_at'] = null;

            $existing = DataTahunAjaran::where('kode_tahun', $payload['kode_tahun'])
                ->where('is_deleted', false)
                ->first();

            $existingDeleted = DataTahunAjaran::where('kode_tahun', $payload['kode_tahun'])
                ->where('is_deleted', true)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $affectedKodeTahun[$payload['kode_tahun']] = $payload['kode_tahun'];
                $updated++;
                continue;
            }

            if ($existingDeleted) {
                $existingDeleted->update($payload);
                $affectedKodeTahun[$payload['kode_tahun']] = $payload['kode_tahun'];
                $updated++;
                continue;
            }

            DataTahunAjaran::create($payload);
            $affectedKodeTahun[$payload['kode_tahun']] = $payload['kode_tahun'];
            $inserted++;
        }

        fclose($handle);

        $affectedTahunAjaran = DataTahunAjaran::query()
            ->withCount([
                'kelas as jumlah_kelas',
                'santri as jumlah_santri',
            ])
            ->whereIn('kode_tahun', array_values($affectedKodeTahun))
            ->where('is_deleted', false)
            ->orderByDesc('id_tahun_ajaran')
            ->get();

        return response()->json([
            'message' => 'Import data tahun ajaran selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
                'affected_tahun_ajaran' => $affectedTahunAjaran,
            ],
        ]);
    }

    /**
     * Export data tahun ajaran ke CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataTahunAjaran::query()
            ->withCount([
                'kelas as jumlah_kelas',
                'santri as jumlah_santri',
            ])
            ->where('is_deleted', false)
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_tahun', 'like', "%{$keyword}%")
                        ->orWhere('nama_tahun', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_tahun_ajaran');

        $headers = ['kode_tahun', 'nama_tahun', 'keterangan', 'status', 'jumlah_kelas', 'jumlah_santri'];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->kode_tahun,
                        $row->nama_tahun,
                        $row->keterangan,
                        $row->status,
                        $row->jumlah_kelas,
                        $row->jumlah_santri,
                    ]);
                }
            });

            fclose($output);
        }, 'data-tahun-ajaran-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Template CSV import data tahun ajaran.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'kode_tahun',
            'nama_tahun',
            'keterangan',
            'status',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-tahun-ajaran.csv', [
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

    private function mapPayload(array $rowData): array
    {
        return [
            'kode_tahun' => $rowData['kode_tahun'] ?? null,
            'nama_tahun' => $rowData['nama_tahun'] ?? null,
            'keterangan' => $rowData['keterangan'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }
}
