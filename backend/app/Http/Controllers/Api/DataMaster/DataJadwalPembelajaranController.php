<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Http\Controllers\Controller;
use App\Models\JadwalPembelajaran;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataJadwalPembelajaranController extends Controller
{
    /**
     * List data jadwal pembelajaran.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = JadwalPembelajaran::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas'])
            ->when($request->filled('id_kelas_mapel'), fn ($q) => $q->where('id_kelas_mapel', (int) $request->id_kelas_mapel))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', trim((string) $request->tahun_ajaran)))
            ->when($request->filled('hari'), fn ($q) => $q->where('hari', strtoupper(trim((string) $request->hari))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper(trim((string) $request->status))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('tahun_ajaran', 'like', "%{$keyword}%")
                        ->orWhere('hari', 'like', "%{$keyword}%")
                        ->orWhere('ruangan', 'like', "%{$keyword}%")
                        ->orWhere('jam_mulai', 'like', "%{$keyword}%")
                        ->orWhere('jam_selesai', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('tahun_ajaran')
            ->orderBy('hari')
            ->orderBy('jam_mulai');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data jadwal pembelajaran baru.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge($this->normalizeJadwalInput($request->all()));

        $validated = $request->validate([
            'id_kelas_mapel' => ['required', 'integer', 'exists:data_kelas_mapel,id_kelas_mapel'],
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'hari' => ['required', 'string', 'max:10'],
            'jam_mulai' => ['required', 'date_format:H:i:s'],
            'jam_selesai' => ['required', 'date_format:H:i:s', 'after:jam_mulai'],
            'ruangan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeJadwalInput($validated);
        $this->validateUniqueCombination($validated, null);

        $data = JadwalPembelajaran::create($validated);

        return response()->json([
            'message' => 'Data jadwal pembelajaran berhasil dibuat.',
            'data' => $data->fresh(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas']),
        ], 201);
    }

    /**
     * Tampilkan detail data jadwal pembelajaran.
     */
    public function show(int $id): JsonResponse
    {
        $data = JadwalPembelajaran::with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas'])
            ->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data jadwal pembelajaran.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $jadwal = JadwalPembelajaran::findOrFail($id);

        $request->merge($this->normalizeJadwalInput($request->all()));

        $validated = $request->validate([
            'id_kelas_mapel' => ['sometimes', 'integer', 'exists:data_kelas_mapel,id_kelas_mapel'],
            'tahun_ajaran' => [
                'sometimes',
                'string',
                'max:20',
                Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
            ],
            'hari' => ['sometimes', 'string', 'max:10'],
            'jam_mulai' => ['sometimes', 'date_format:H:i:s'],
            'jam_selesai' => ['sometimes', 'date_format:H:i:s'],
            'ruangan' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $validated = $this->normalizeJadwalInput($validated);

        $merged = [
            'id_kelas_mapel' => $validated['id_kelas_mapel'] ?? $jadwal->id_kelas_mapel,
            'tahun_ajaran' => $validated['tahun_ajaran'] ?? $jadwal->tahun_ajaran,
            'hari' => $validated['hari'] ?? $jadwal->hari,
            'jam_mulai' => $validated['jam_mulai'] ?? $jadwal->jam_mulai,
        ];

        $this->validateUniqueCombination($merged, $jadwal->id_jadwal);

        $jamMulai = $validated['jam_mulai'] ?? $jadwal->jam_mulai;
        $jamSelesai = $validated['jam_selesai'] ?? $jadwal->jam_selesai;
        if ($jamMulai >= $jamSelesai) {
            throw ValidationException::withMessages([
                'jam_selesai' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        $jadwal->update($validated);

        return response()->json([
            'message' => 'Data jadwal pembelajaran berhasil diperbarui.',
            'data' => $jadwal->fresh(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'kelasMapel.petugas']),
        ]);
    }

    /**
     * Hapus data jadwal pembelajaran.
     */
    public function destroy(int $id): JsonResponse
    {
        $jadwal = JadwalPembelajaran::findOrFail($id);

        try {
            $jadwal->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data jadwal pembelajaran tidak dapat dihapus karena masih dipakai pada data terkait lainnya.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data jadwal pembelajaran berhasil dihapus.',
        ]);
    }

    /**
     * Import data jadwal pembelajaran dari CSV (upsert berdasarkan kombinasi unik).
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

            $payload = $this->mapJadwalPayload($rowData);
            $payload = $this->normalizeJadwalInput($payload);

            $validator = Validator::make($payload, [
                'id_kelas_mapel' => ['required', 'integer', 'exists:data_kelas_mapel,id_kelas_mapel'],
                'tahun_ajaran' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::exists('data_tahun_ajaran', 'kode_tahun')->where(fn ($q) => $q->where('is_deleted', false)),
                ],
                'hari' => ['required', 'string', 'max:10'],
                'jam_mulai' => ['required', 'date_format:H:i:s'],
                'jam_selesai' => ['required', 'date_format:H:i:s', 'after:jam_mulai'],
                'ruangan' => ['nullable', 'string', 'max:50'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = JadwalPembelajaran::query()
                ->where('id_kelas_mapel', $payload['id_kelas_mapel'])
                ->where('tahun_ajaran', $payload['tahun_ajaran'])
                ->where('hari', $payload['hari'])
                ->where('jam_mulai', $payload['jam_mulai'])
                ->first();

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            JadwalPembelajaran::create($payload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data jadwal pembelajaran selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data jadwal pembelajaran ke CSV sesuai filter.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = JadwalPembelajaran::query()
            ->when($request->filled('id_kelas_mapel'), fn ($q) => $q->where('id_kelas_mapel', (int) $request->id_kelas_mapel))
            ->when($request->filled('tahun_ajaran'), fn ($q) => $q->where('tahun_ajaran', trim((string) $request->tahun_ajaran)))
            ->when($request->filled('hari'), fn ($q) => $q->where('hari', strtoupper(trim((string) $request->hari))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper(trim((string) $request->status))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('tahun_ajaran', 'like', "%{$keyword}%")
                        ->orWhere('hari', 'like', "%{$keyword}%")
                        ->orWhere('ruangan', 'like', "%{$keyword}%")
                        ->orWhere('jam_mulai', 'like', "%{$keyword}%")
                        ->orWhere('jam_selesai', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('tahun_ajaran')
            ->orderBy('hari')
            ->orderBy('jam_mulai');

        $headers = [
            'id_kelas_mapel',
            'tahun_ajaran',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'ruangan',
            'status',
        ];

        return response()->streamDownload(function () use ($query, $headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            $query->chunk(500, function ($rows) use ($output) {
                foreach ($rows as $row) {
                    fputcsv($output, [
                        $row->id_kelas_mapel,
                        $row->tahun_ajaran,
                        $row->hari,
                        $row->jam_mulai,
                        $row->jam_selesai,
                        $row->ruangan,
                        $row->status,
                    ]);
                }
            });

            fclose($output);
        }, 'data-jadwal-pembelajaran-' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Template CSV import data jadwal pembelajaran.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'id_kelas_mapel',
            'tahun_ajaran',
            'hari',
            'jam_mulai',
            'jam_selesai',
            'ruangan',
            'status',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-jadwal-pembelajaran.csv', [
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

    private function mapJadwalPayload(array $rowData): array
    {
        return [
            'id_kelas_mapel' => $rowData['id_kelas_mapel'] ?? null,
            'tahun_ajaran' => $rowData['tahun_ajaran'] ?? null,
            'hari' => $rowData['hari'] ?? null,
            'jam_mulai' => $rowData['jam_mulai'] ?? null,
            'jam_selesai' => $rowData['jam_selesai'] ?? null,
            'ruangan' => $rowData['ruangan'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }

    private function normalizeJadwalInput(array $payload): array
    {
        foreach (['hari', 'status'] as $field) {
            if (array_key_exists($field, $payload) && is_string($payload[$field])) {
                $payload[$field] = strtoupper(trim($payload[$field]));
            }
        }

        if (array_key_exists('tahun_ajaran', $payload) && is_string($payload['tahun_ajaran'])) {
            $payload['tahun_ajaran'] = trim($payload['tahun_ajaran']);
        }

        if (array_key_exists('ruangan', $payload) && is_string($payload['ruangan'])) {
            $payload['ruangan'] = trim($payload['ruangan']);
        }

        return $payload;
    }

    private function validateUniqueCombination(array $payload, ?int $ignoreId): void
    {
        $query = JadwalPembelajaran::query()
            ->where('id_kelas_mapel', $payload['id_kelas_mapel'])
            ->where('tahun_ajaran', $payload['tahun_ajaran'])
            ->where('hari', $payload['hari'])
            ->where('jam_mulai', $payload['jam_mulai']);

        if ($ignoreId !== null) {
            $query->where('id_jadwal', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'id_kelas_mapel' => ['Kombinasi jadwal sudah ada untuk kelas mapel, tahun ajaran, hari, dan jam mulai yang sama.'],
            ]);
        }
    }
}
