<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Exports\DataMataPelajaranExport;
use App\Http\Controllers\Controller;
use App\Imports\DataMataPelajaranImport;
use App\Models\DataMataPelajaran;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataMataPelajaranController extends Controller
{
    /**
     * List data mata pelajaran.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataMataPelajaran::query()
            ->when($request->filled('kode_unit'), fn ($q) => $q->where('kode_unit', strtoupper($request->kode_unit)))
            ->when($request->filled('kelompok_mapel'), fn ($q) => $q->where('kelompok_mapel', $request->kelompok_mapel))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_mapel', 'like', "%{$keyword}%")
                        ->orWhere('nama_mapel', 'like', "%{$keyword}%")
                        ->orWhere('kelompok_mapel', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('urutan')
            ->orderBy('nama_mapel');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data mata pelajaran baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge($this->normalizeMapelInput($request->all()));

        $validated = $request->validate([
            'kode_mapel' => ['required', 'string', 'max:20', 'unique:data_mata_pelajaran,kode_mapel'],
            'nama_mapel' => ['required', 'string', 'max:200'],
            'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'kelompok_mapel' => ['nullable', 'string', 'max:50'],
            'urutan' => ['nullable', 'integer'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeMapelInput($validated);

        $data = DataMataPelajaran::create($validated);

        return response()->json([
            'message' => 'Data mata pelajaran berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data mata pelajaran.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataMataPelajaran::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data mata pelajaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $mapel = DataMataPelajaran::findOrFail($id);

        $request->merge($this->normalizeMapelInput($request->all()));

        $validated = $request->validate([
            'kode_mapel' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('data_mata_pelajaran', 'kode_mapel')->ignore($mapel->id_mapel, 'id_mapel'),
            ],
            'nama_mapel' => ['sometimes', 'string', 'max:200'],
            'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
            'kelompok_mapel' => ['nullable', 'string', 'max:50'],
            'urutan' => ['nullable', 'integer'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeMapelInput($validated);

        $mapel->update($validated);

        return response()->json([
            'message' => 'Data mata pelajaran berhasil diperbarui.',
            'data' => $mapel->fresh(),
        ]);
    }

    /**
     * Hapus data mata pelajaran.
     */
    public function destroy(int $id): JsonResponse
    {
        $mapel = DataMataPelajaran::findOrFail($id);

        try {
            $mapel->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data mata pelajaran tidak dapat dihapus karena masih dipakai pada data kelas mapel/KKM atau data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data mata pelajaran berhasil dihapus.',
        ]);
    }

    /**
     * Import data mata pelajaran dari CSV (upsert berdasarkan kode_mapel).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $import = new DataMataPelajaranImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Import data mata pelajaran selesai.',
                'data' => $import->result(),
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

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            $rowData = $this->combineRowData($normalizedHeaders, $row);
            if ($this->isEmptyRow($rowData)) {
                continue;
            }

            $payload = $this->mapMapelPayload($rowData);
            $payload = $this->normalizeMapelInput($payload);

            $validator = Validator::make($payload, [
                'kode_mapel' => ['required', 'string', 'max:20'],
                'nama_mapel' => ['required', 'string', 'max:200'],
                'kode_unit' => ['nullable', 'string', 'max:10', 'exists:data_unit,kode_unit'],
                'kelompok_mapel' => ['nullable', 'string', 'max:50'],
                'urutan' => ['nullable', 'integer'],
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

            $existing = DataMataPelajaran::where('kode_mapel', $payload['kode_mapel'])->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            DataMataPelajaran::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data mata pelajaran selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data mata pelajaran ke CSV sesuai filter.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new DataMataPelajaranExport(
                kodeUnit: $request->filled('kode_unit') ? (string) $request->kode_unit : null,
                kelompokMapel: $request->filled('kelompok_mapel') ? (string) $request->kelompok_mapel : null,
                status: $request->filled('status') ? (string) $request->status : null,
                keyword: $request->filled('q') ? (string) $request->q : null,
            ),
            'data-mata-pelajaran-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Template CSV import data mata pelajaran.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'kode_mapel',
            'nama_mapel',
            'kode_unit',
            'kelompok_mapel',
            'urutan',
            'keterangan',
            'status',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-mata-pelajaran.csv', [
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

    private function mapMapelPayload(array $rowData): array
    {
        return [
            'kode_mapel' => $rowData['kode_mapel'] ?? null,
            'nama_mapel' => $rowData['nama_mapel'] ?? null,
            'kode_unit' => $rowData['kode_unit'] ?? null,
            'kelompok_mapel' => $rowData['kelompok_mapel'] ?? null,
            'urutan' => $rowData['urutan'] ?? null,
            'keterangan' => $rowData['keterangan'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }

    private function normalizeMapelInput(array $payload): array
    {
        foreach (['kode_mapel', 'kode_unit', 'status'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = strtoupper(trim($payload[$field]));
            }
        }

        return $payload;
    }
}
