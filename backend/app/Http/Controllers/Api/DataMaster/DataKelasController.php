<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Exports\DataKelasExport;
use App\Http\Controllers\Controller;
use App\Imports\DataKelasImport;
use App\Models\DataKelas;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataKelasController extends Controller
{
    /**
     * List data kelas.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataKelas::query()
            ->with(['unit', 'tahunAjaranRelasi'])
            ->withCount([
                'santri as jumlah_santri',
                'santriAktif as jumlah_santri_aktif',
                'santriLulus as jumlah_santri_lulus',
                'santriKeluar as jumlah_santri_keluar',
            ])
            ->where('is_deleted', false)
            ->when($request->filled('kode_unit'), fn ($q) => $q->where('kode_unit', strtoupper($request->kode_unit)))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('status_ppdb'), fn ($q) => $q->where('status_ppdb', strtoupper($request->status_ppdb)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('nama_kelas', 'like', "%{$keyword}%")
                        ->orWhere('nama_jurusan', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('kode_unit')
            ->orderBy('nama_kelas');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data kelas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_unit' => ['required', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'kode_kelas' => [
                'required',
                'string',
                'max:10',
                Rule::unique('data_kelas', 'kode_kelas')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'nama_kelas' => ['required', 'string', 'max:100'],
            'nama_jurusan' => ['nullable', 'string', 'max:100'],
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            'status_ppdb' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated['kode_unit'] = strtoupper($validated['kode_unit']);
        $validated['kode_kelas'] = strtoupper($validated['kode_kelas']);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        if (array_key_exists('status_ppdb', $validated) && $validated['status_ppdb'] !== null) {
            $validated['status_ppdb'] = strtoupper($validated['status_ppdb']);
        }

        $existingDeleted = DataKelas::where('kode_kelas', $validated['kode_kelas'])
            ->where('is_deleted', true)
            ->first();

        if ($existingDeleted) {
            $existingDeleted->update([
                'kode_unit' => $validated['kode_unit'],
                'nama_kelas' => $validated['nama_kelas'],
                'nama_jurusan' => $validated['nama_jurusan'] ?? null,
                'tahun_ajaran' => $validated['tahun_ajaran'],
                'status' => $validated['status'] ?? 'AKTIF',
                'status_ppdb' => $validated['status_ppdb'] ?? null,
                'is_deleted' => false,
                'deleted_at' => null,
            ]);

            $data = $existingDeleted->fresh();
        } else {
            $validated['is_deleted'] = false;
            $validated['deleted_at'] = null;
            $data = DataKelas::create($validated);
        }

        $data->load(['unit', 'tahunAjaranRelasi']);
        $data->loadCount([
            'santri as jumlah_santri',
            'santriAktif as jumlah_santri_aktif',
            'santriLulus as jumlah_santri_lulus',
            'santriKeluar as jumlah_santri_keluar',
        ]);

        return response()->json([
            'message' => 'Data kelas berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data kelas.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataKelas::query()
            ->with(['unit', 'tahunAjaranRelasi'])
            ->withCount([
                'santri as jumlah_santri',
                'santriAktif as jumlah_santri_aktif',
                'santriLulus as jumlah_santri_lulus',
                'santriKeluar as jumlah_santri_keluar',
            ])
            ->where('is_deleted', false)
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data kelas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kelas = DataKelas::where('is_deleted', false)->findOrFail($id);

        $validated = $request->validate([
            'kode_unit' => ['sometimes', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'kode_kelas' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('data_kelas', 'kode_kelas')
                    ->ignore($kelas->id_kelas, 'id_kelas')
                    ->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'nama_kelas' => ['sometimes', 'string', 'max:100'],
            'nama_jurusan' => ['nullable', 'string', 'max:100'],
            'tahun_ajaran' => [
                'sometimes',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            'status_ppdb' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (array_key_exists('kode_unit', $validated) && $validated['kode_unit'] !== null) {
            $validated['kode_unit'] = strtoupper($validated['kode_unit']);
        }

        if (array_key_exists('kode_kelas', $validated) && $validated['kode_kelas'] !== null) {
            $validated['kode_kelas'] = strtoupper($validated['kode_kelas']);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        if (array_key_exists('status_ppdb', $validated) && $validated['status_ppdb'] !== null) {
            $validated['status_ppdb'] = strtoupper($validated['status_ppdb']);
        }

        $kelas->update($validated);
        $kelas->load(['unit', 'tahunAjaranRelasi']);
        $kelas->loadCount([
            'santri as jumlah_santri',
            'santriAktif as jumlah_santri_aktif',
            'santriLulus as jumlah_santri_lulus',
            'santriKeluar as jumlah_santri_keluar',
        ]);

        return response()->json([
            'message' => 'Data kelas berhasil diperbarui.',
            'data' => $kelas,
        ]);
    }

    /**
     * Hapus data kelas.
     */
    public function destroy(int $id): JsonResponse
    {
        $kelas = DataKelas::where('is_deleted', false)->findOrFail($id);

        $kelas->update([
            'is_deleted' => true,
            'deleted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Data kelas dipindahkan ke trash.',
        ]);
    }

    /**
     * List data kelas di trash.
     */
    public function trash(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataKelas::query()
            ->with(['unit', 'tahunAjaranRelasi'])
            ->withCount([
                'santri as jumlah_santri',
                'santriAktif as jumlah_santri_aktif',
                'santriLulus as jumlah_santri_lulus',
                'santriKeluar as jumlah_santri_keluar',
            ])
            ->where('is_deleted', true)
            ->when($request->filled('kode_unit'), fn ($q) => $q->where('kode_unit', strtoupper($request->kode_unit)))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', $request->tahun_ajaran))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('nama_kelas', 'like', "%{$keyword}%")
                        ->orWhere('nama_jurusan', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->orderBy('kode_unit')
            ->orderBy('nama_kelas');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Pulihkan data kelas dari trash.
     */
    public function restore(int $id): JsonResponse
    {
        $kelas = DataKelas::where('is_deleted', true)->findOrFail($id);

        $activeConflict = DataKelas::query()
            ->where('kode_kelas', $kelas->kode_kelas)
            ->where('is_deleted', false)
            ->where('id_kelas', '!=', $kelas->id_kelas)
            ->exists();

        if ($activeConflict) {
            return response()->json([
                'message' => 'Data kelas tidak dapat dipulihkan karena kode_kelas sudah dipakai data aktif lain.',
            ], 422);
        }

        $kelas->update([
            'is_deleted' => false,
            'deleted_at' => null,
        ]);

        $kelas->load(['unit', 'tahunAjaranRelasi']);
        $kelas->loadCount([
            'santri as jumlah_santri',
            'santriAktif as jumlah_santri_aktif',
            'santriLulus as jumlah_santri_lulus',
            'santriKeluar as jumlah_santri_keluar',
        ]);

        return response()->json([
            'message' => 'Data kelas berhasil dipulihkan.',
            'data' => $kelas,
        ]);
    }

    /**
     * Ringkasan ketergantungan data kelas.
     */
    public function dependencySummary(int $id): JsonResponse
    {
        $kelas = DataKelas::findOrFail($id);
        $dependencies = $this->kelasDependenciesByKodeKelas($kelas->kode_kelas);

        return response()->json([
            'data' => [
                'id_kelas' => $kelas->id_kelas,
                'kode_kelas' => $kelas->kode_kelas,
                'is_deleted' => (bool) $kelas->is_deleted,
                'dependencies' => $dependencies,
                'can_force_delete' => $dependencies['total'] === 0,
            ],
        ]);
    }

    /**
     * Hapus permanen data kelas dari trash.
     */
    public function forceDelete(int $id): JsonResponse
    {
        $kelas = DataKelas::where('is_deleted', true)->findOrFail($id);
        $dependencies = $this->kelasDependenciesByKodeKelas($kelas->kode_kelas);

        if ($dependencies['total'] > 0) {
            return response()->json([
                'message' => 'Data kelas tidak dapat dihapus permanen karena masih dipakai data lain.',
                'data' => [
                    'dependencies' => $dependencies,
                ],
            ], 422);
        }

        try {
            $kelas->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data kelas tidak dapat dihapus permanen karena masih dipakai data lain.',
                'data' => [
                    'dependencies' => $this->kelasDependenciesByKodeKelas($kelas->kode_kelas),
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Data kelas berhasil dihapus permanen.',
        ]);
    }

    /**
     * Import data kelas dari CSV (upsert berdasarkan kode_kelas).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $import = new DataKelasImport();
            Excel::import($import, $request->file('file'));

            $result = $import->result();
            $affectedKelas = DataKelas::query()
                ->with(['unit', 'tahunAjaranRelasi'])
                ->withCount([
                    'santri as jumlah_santri',
                    'santriAktif as jumlah_santri_aktif',
                    'santriLulus as jumlah_santri_lulus',
                    'santriKeluar as jumlah_santri_keluar',
                ])
                ->where('is_deleted', false)
                ->whereIn('kode_kelas', $import->affectedKodeKelas())
                ->orderBy('kode_unit')
                ->orderBy('nama_kelas')
                ->get();

            return response()->json([
                'message' => 'Import data kelas selesai.',
                'data' => array_merge($result, [
                    'affected_kelas' => $affectedKelas,
                ]),
            ]);
        }

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
        $affectedKodeKelas = [];

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $rowData = $this->combineRowData($normalizedHeaders, $row);
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $payload = $this->mapKelasPayload($rowData);

            $validator = Validator::make($payload, [
                'kode_unit' => ['required', 'string', 'max:10', 'exists:data_unit,kode_unit'],
                'kode_kelas' => ['required', 'string', 'max:10'],
                'nama_kelas' => ['required', 'string', 'max:100'],
                'nama_jurusan' => ['nullable', 'string', 'max:100'],
                'tahun_ajaran' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
                ],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
                'status_ppdb' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $payload['kode_unit'] = strtoupper($payload['kode_unit']);
            $payload['kode_kelas'] = strtoupper($payload['kode_kelas']);

            if (!empty($payload['status'])) {
                $payload['status'] = strtoupper($payload['status']);
            }

            if (!empty($payload['status_ppdb'])) {
                $payload['status_ppdb'] = strtoupper($payload['status_ppdb']);
            }

            $payload['is_deleted'] = false;
            $payload['deleted_at'] = null;

            $existing = DataKelas::where('kode_kelas', $payload['kode_kelas'])
                ->where('is_deleted', false)
                ->first();

            $existingDeleted = DataKelas::where('kode_kelas', $payload['kode_kelas'])
                ->where('is_deleted', true)
                ->first();

            if ($existing) {
                $existing->update($payload);
                $affectedKodeKelas[$payload['kode_kelas']] = $payload['kode_kelas'];
                $updated++;
                continue;
            }

            if ($existingDeleted) {
                $existingDeleted->update($payload);
                $affectedKodeKelas[$payload['kode_kelas']] = $payload['kode_kelas'];
                $updated++;
                continue;
            }

            DataKelas::create($payload);
            $affectedKodeKelas[$payload['kode_kelas']] = $payload['kode_kelas'];
            $inserted++;
        }

        fclose($handle);

        $affectedKelas = DataKelas::query()
            ->with(['unit', 'tahunAjaranRelasi'])
            ->withCount([
                'santri as jumlah_santri',
                'santriAktif as jumlah_santri_aktif',
                'santriLulus as jumlah_santri_lulus',
                'santriKeluar as jumlah_santri_keluar',
            ])
            ->where('is_deleted', false)
            ->whereIn('kode_kelas', array_values($affectedKodeKelas))
            ->orderBy('kode_unit')
            ->orderBy('nama_kelas')
            ->get();

        return response()->json([
            'message' => 'Import data kelas selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
                'affected_kelas' => $affectedKelas,
            ],
        ]);
    }

    /**
     * Export data kelas ke CSV sesuai filter.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new DataKelasExport(
                kodeUnit: $request->filled('kode_unit') ? (string) $request->kode_unit : null,
                tahunAjaran: $request->filled('tahun_ajaran') ? (string) $request->tahun_ajaran : null,
                status: $request->filled('status') ? (string) $request->status : null,
                statusPpdb: $request->filled('status_ppdb') ? (string) $request->status_ppdb : null,
                keyword: $request->filled('q') ? (string) $request->q : null,
            ),
            'data-kelas-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Template CSV import data kelas.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'kode_unit',
            'kode_kelas',
            'nama_kelas',
            'nama_jurusan',
            'tahun_ajaran',
            'status',
            'status_ppdb',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-kelas.csv', [
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

    private function mapKelasPayload(array $rowData): array
    {
        return [
            'kode_unit' => $rowData['kode_unit'] ?? null,
            'kode_kelas' => $rowData['kode_kelas'] ?? null,
            'nama_kelas' => $rowData['nama_kelas'] ?? null,
            'nama_jurusan' => $rowData['nama_jurusan'] ?? null,
            'tahun_ajaran' => $rowData['tahun_ajaran'] ?? null,
            'status' => $rowData['status'] ?? null,
            'status_ppdb' => $rowData['status_ppdb'] ?? null,
        ];
    }

    private function kelasDependenciesByKodeKelas(string $kodeKelas): array
    {
        $safeCount = function (string $table, string $column) use ($kodeKelas): int {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                return 0;
            }

            return DB::table($table)->where($column, $kodeKelas)->count();
        };

        $dependencies = [
            'data_santri' => $safeCount('data_santri', 'kode_kelas'),
            'data_kelas_mapel' => $safeCount('data_kelas_mapel', 'kode_kelas'),
            'data_nilai_siswa' => $safeCount('data_nilai_siswa', 'kode_kelas'),
            'data_raport' => $safeCount('data_raport', 'kode_kelas'),
            'ppdb_pendaftar' => $safeCount('ppdb_pendaftar', 'kode_kelas_diterima'),
        ];

        $dependencies['total'] = array_sum($dependencies);

        return $dependencies;
    }
}
