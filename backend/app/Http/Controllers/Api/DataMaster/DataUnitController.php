<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Exports\DataUnitExport;
use App\Http\Controllers\Controller;
use App\Imports\DataUnitImport;
use App\Models\DataUnit;
use Illuminate\Database\QueryException;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DataUnitController extends Controller
{
    /**
     * List data unit.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataUnit::query()
            ->withCount([
                'kelas as jumlah_kelas' => fn ($q) => $q->where('data_kelas.status', 'AKTIF')->where('data_kelas.is_deleted', false),
                'santri as jumlah_santri' => fn ($q) => $q->where('data_santri.status', 'AKTIF')->where('data_santri.is_deleted', false),
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_unit', 'like', "%{$keyword}%")
                        ->orWhere('nama_unit', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('kode_unit')
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
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated['kode_unit'] = strtoupper($validated['kode_unit']);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
        }

        $data = DataUnit::create($validated);
        $data->loadCount([
            'kelas as jumlah_kelas' => fn ($q) => $q->where('data_kelas.status', 'AKTIF')->where('data_kelas.is_deleted', false),
            'santri as jumlah_santri' => fn ($q) => $q->where('data_santri.status', 'AKTIF')->where('data_santri.is_deleted', false),
        ]);

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
        $data = DataUnit::query()
            ->withCount([
                'kelas as jumlah_kelas' => fn ($q) => $q->where('data_kelas.status', 'AKTIF')->where('data_kelas.is_deleted', false),
                'santri as jumlah_santri' => fn ($q) => $q->where('data_santri.status', 'AKTIF')->where('data_santri.is_deleted', false),
            ])
            ->findOrFail($id);

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
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (array_key_exists('kode_unit', $validated) && $validated['kode_unit'] !== null) {
            $validated['kode_unit'] = strtoupper($validated['kode_unit']);
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
            if ($validated['status'] === 'NONAKTIF') {
                $hasActiveKelas = \App\Models\DataKelas::where('kode_unit', $unit->kode_unit)
                    ->where('is_deleted', false)
                    ->where('status', 'AKTIF')
                    ->exists();
                if ($hasActiveKelas) {
                    return response()->json([
                        'message' => 'Tidak dapat menonaktifkan unit karena masih digunakan oleh kelas aktif.',
                    ], 422);
                }
            }
        }
        $unit->update($validated);
        $unit->loadCount([
            'kelas as jumlah_kelas' => fn ($q) => $q->where('data_kelas.status', 'AKTIF')->where('data_kelas.is_deleted', false),
            'santri as jumlah_santri' => fn ($q) => $q->where('data_santri.status', 'AKTIF')->where('data_santri.is_deleted', false),
        ]);

        return response()->json([
            'message' => 'Data unit berhasil diperbarui.',
            'data' => $unit,
        ]);
    }

    /**
     * Hapus data unit.
     */
    public function destroy(int $id): JsonResponse
    {
        $unit = DataUnit::findOrFail($id);

        $hasKelas = \App\Models\DataKelas::where('kode_unit', $unit->kode_unit)
            ->where('is_deleted', false)
            ->exists();
            
        if ($hasKelas) {
            return response()->json([
                'message' => 'Data unit tidak dapat dihapus karena masih digunakan oleh data kelas.',
            ], 422);
        }

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
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $import = new DataUnitImport();
            Excel::import($import, $request->file('file'));

            $result = $import->result();
            $affectedUnits = DataUnit::query()
                ->withCount([
                    'kelas as jumlah_kelas' => fn ($q) => $q->where('data_kelas.status', 'AKTIF')->where('data_kelas.is_deleted', false),
                    'santri as jumlah_santri' => fn ($q) => $q->where('data_santri.status', 'AKTIF')->where('data_santri.is_deleted', false),
                ])
                ->whereIn('kode_unit', $import->affectedKodeUnit())
                ->orderBy('kode_unit')
                ->orderBy('nama_unit')
                ->get();

            return response()->json([
                'message' => 'Import data unit selesai.',
                'data' => array_merge($result, [
                    'affected_units' => $affectedUnits,
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
        $affectedKodeUnit = [];

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

            $payload['kode_unit'] = strtoupper($payload['kode_unit']);

            if (!empty($payload['status'])) {
                $payload['status'] = strtoupper($payload['status']);
            }

            $existing = DataUnit::where('kode_unit', $payload['kode_unit'])->first();

            if ($existing) {
                $existing->update($payload);
                $affectedKodeUnit[$payload['kode_unit']] = $payload['kode_unit'];
                $updated++;
                continue;
            }

            DataUnit::create($payload);
            $affectedKodeUnit[$payload['kode_unit']] = $payload['kode_unit'];
            $inserted++;
        }

        fclose($handle);

        $affectedUnits = DataUnit::query()
            ->withCount([
                'kelas as jumlah_kelas' => fn ($q) => $q->where('data_kelas.status', 'AKTIF')->where('data_kelas.is_deleted', false),
                'santri as jumlah_santri' => fn ($q) => $q->where('data_santri.status', 'AKTIF')->where('data_santri.is_deleted', false),
            ])
            ->whereIn('kode_unit', array_values($affectedKodeUnit))
            ->orderBy('kode_unit')
            ->orderBy('nama_unit')
            ->get();

        return response()->json([
            'message' => 'Import data unit selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
                'affected_units' => $affectedUnits,
            ],
        ]);
    }

    /**
     * Export data unit ke Excel (.xlsx) sesuai filter.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new DataUnitExport(
                status: $request->filled('status') ? (string) $request->status : null,
                keyword: $request->filled('q') ? (string) $request->q : null,
            ),
            'data-unit-' . now()->format('Ymd_His') . '.xlsx'
        );
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
            'keterangan' => $rowData['keterangan'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }
}
