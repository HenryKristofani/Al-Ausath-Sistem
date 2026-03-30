<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\DataUnit;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataUnitController extends Controller
{
    /**
     * List data unit.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataUnit::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('status_ppdb'), fn ($q) => $q->where('status_ppdb', strtoupper($request->status_ppdb)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_unit', 'like', "%{$keyword}%")
                        ->orWhere('nama_unit', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nomor_urut')
            ->orderBy('nama_unit');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data unit baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode_unit' => ['required', 'string', 'max:10', 'unique:data_unit,kode_unit'],
            'nama_unit' => ['required', 'string', 'max:100'],
            'nomor_urut' => ['nullable', 'integer'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            'status_ppdb' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated['kode_unit'] = strtoupper($validated['kode_unit']);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        if (array_key_exists('status_ppdb', $validated) && $validated['status_ppdb'] !== null) {
            $validated['status_ppdb'] = strtoupper($validated['status_ppdb']);
        }

        $data = DataUnit::create($validated);

        return response()->json([
            'message' => 'Data unit berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data unit.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataUnit::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data unit.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $unit = DataUnit::findOrFail($id);

        $validated = $request->validate([
            'kode_unit' => [
                'sometimes',
                'string',
                'max:10',
                Rule::unique('data_unit', 'kode_unit')->ignore($unit->id_unit, 'id_unit'),
            ],
            'nama_unit' => ['sometimes', 'string', 'max:100'],
            'nomor_urut' => ['nullable', 'integer'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            'status_ppdb' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (array_key_exists('kode_unit', $validated) && $validated['kode_unit'] !== null) {
            $validated['kode_unit'] = strtoupper($validated['kode_unit']);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        if (array_key_exists('status_ppdb', $validated) && $validated['status_ppdb'] !== null) {
            $validated['status_ppdb'] = strtoupper($validated['status_ppdb']);
        }

        $unit->update($validated);

        return response()->json([
            'message' => 'Data unit berhasil diperbarui.',
            'data' => $unit->fresh(),
        ]);
    }

    /**
     * Hapus data unit.
     */
    public function destroy(int $id): JsonResponse
    {
        $unit = DataUnit::findOrFail($id);

        try {
            $unit->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data unit tidak dapat dihapus karena masih dipakai pada data kelas/mapel/rekening atau data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data unit berhasil dihapus.',
        ]);
    }

    /**
     * Import data unit dari CSV (upsert berdasarkan kode_unit).
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

            $payload = $this->mapUnitPayload($rowData);

            $validator = Validator::make($payload, [
                'kode_unit' => ['required', 'string', 'max:10'],
                'nama_unit' => ['required', 'string', 'max:100'],
                'nomor_urut' => ['nullable', 'integer'],
                'keterangan' => ['nullable', 'string'],
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

            if (!empty($payload['status'])) {
                $payload['status'] = strtoupper($payload['status']);
            }

            if (!empty($payload['status_ppdb'])) {
                $payload['status_ppdb'] = strtoupper($payload['status_ppdb']);
            }

            $existing = DataUnit::where('kode_unit', $payload['kode_unit'])->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            DataUnit::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data unit selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data unit ke CSV sesuai filter.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = DataUnit::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('status_ppdb'), fn ($q) => $q->where('status_ppdb', strtoupper($request->status_ppdb)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_unit', 'like', "%{$keyword}%")
                        ->orWhere('nama_unit', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nomor_urut')
            ->orderBy('nama_unit');

        $headers = [
            'kode_unit',
            'nama_unit',
            'nomor_urut',
            'keterangan',
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
                        $row->nama_unit,
                        $row->nomor_urut,
                        $row->keterangan,
                        $row->status,
                        $row->status_ppdb,
                    ]);
                }
            });

            fclose($output);
        }, 'data-unit-' . now()->format('Ymd_His') . '.csv', [
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

    private function mapUnitPayload(array $rowData): array
    {
        return [
            'kode_unit' => $rowData['kode_unit'] ?? null,
            'nama_unit' => $rowData['nama_unit'] ?? null,
            'nomor_urut' => $rowData['nomor_urut'] ?? null,
            'keterangan' => $rowData['keterangan'] ?? null,
            'status' => $rowData['status'] ?? null,
            'status_ppdb' => $rowData['status_ppdb'] ?? null,
        ];
    }
}
