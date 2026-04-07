<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\DataKelas;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
            'kode_kelas' => ['required', 'string', 'max:10', 'unique:data_kelas,kode_kelas'],
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

        $data = DataKelas::create($validated);

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
        $data = DataKelas::with(['unit', 'tahunAjaranRelasi'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data kelas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $kelas = DataKelas::findOrFail($id);

        $validated = $request->validate([
            'kode_unit' => ['sometimes', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'kode_kelas' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('data_kelas', 'kode_kelas')->ignore($kelas->id_kelas, 'id_kelas'),
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

        return response()->json([
            'message' => 'Data kelas berhasil diperbarui.',
            'data' => $kelas->fresh(['unit', 'tahunAjaranRelasi']),
        ]);
    }

    /**
     * Hapus data kelas.
     */
    public function destroy(int $id): JsonResponse
    {
        $kelas = DataKelas::findOrFail($id);

        try {
            $kelas->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data kelas tidak dapat dihapus karena masih dipakai pada data santri/mapel atau data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data kelas berhasil dihapus.',
        ]);
    }

    /**
     * Import data kelas dari CSV (upsert berdasarkan kode_kelas).
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

            $existing = DataKelas::where('kode_kelas', $payload['kode_kelas'])->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            DataKelas::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data kelas selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data kelas ke CSV sesuai filter.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataKelas::query()
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

        $headers = [
            'kode_unit',
            'kode_kelas',
            'nama_kelas',
            'nama_jurusan',
            'tahun_ajaran',
            'status',
            'status_ppdb',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->kode_unit,
                        $row->kode_kelas,
                        $row->nama_kelas,
                        $row->nama_jurusan,
                        $row->tahun_ajaran,
                        $row->status,
                        $row->status_ppdb,
                    ]);
                }
            });

            fclose($output);
        }, 'data-kelas-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
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
}
