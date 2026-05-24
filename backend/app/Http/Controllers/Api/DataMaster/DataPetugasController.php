<?php

namespace App\Http\Controllers\Api\DataMaster;

use App\Exports\DataPetugasExport;
use App\Http\Controllers\Controller;
use App\Imports\DataPetugasImport;
use App\Models\DataPetugas;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataPetugasController extends Controller
{
    /**
     * List data petugas.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = DataPetugas::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', strtoupper($request->status)))
            ->when($request->filled('peran_akun'), fn ($q) => $q->where('peran_akun', $request->peran_akun))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = $request->q;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('nama_lengkap', 'like', "%{$keyword}%")
                        ->orWhere('nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('alamat_email', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id_petugas');

        return response()->json($query->paginate($perPage));
    }

    /**
     * Simpan data petugas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nomor_induk' => ['nullable', 'string', 'max:20', 'unique:data_petugas,nomor_induk'],
            'nama_lengkap' => ['required', 'string', 'max:200'],
            'peran_akun' => ['required', 'string', Rule::in(DataPetugas::PERAN_AKUN_OPTIONS)],
            'pilihan_unit' => ['nullable', 'string', 'max:10'],
            'alamat_email' => ['required', 'email', 'max:100', 'unique:data_petugas,alamat_email'],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $data = DataPetugas::create([
            'nomor_induk' => $validated['nomor_induk'] ?? null,
            'nama_lengkap' => $validated['nama_lengkap'],
            'peran_akun' => $validated['peran_akun'],
            'pilihan_unit' => $validated['pilihan_unit'] ?? null,
            'alamat_email' => $validated['alamat_email'],
            'nomor_telepon' => $validated['nomor_telepon'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'status' => strtoupper($validated['status'] ?? 'AKTIF'),
        ]);

        return response()->json([
            'message' => 'Data petugas berhasil dibuat.',
            'data' => $data,
        ], 201);
    }

    /**
     * Tampilkan detail data petugas.
     */
    public function show(int $id): JsonResponse
    {
        $data = DataPetugas::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    /**
     * Perbarui data petugas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $petugas = DataPetugas::findOrFail($id);

        $validated = $request->validate([
            'nomor_induk' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('data_petugas', 'nomor_induk')->ignore($petugas->id_petugas, 'id_petugas'),
            ],
            'nama_lengkap' => ['sometimes', 'string', 'max:200'],
            'peran_akun' => ['sometimes', 'string', Rule::in(DataPetugas::PERAN_AKUN_OPTIONS)],
            'pilihan_unit' => ['nullable', 'string', 'max:10'],
            'alamat_email' => [
                'sometimes',
                'email',
                'max:100',
                Rule::unique('data_petugas', 'alamat_email')->ignore($petugas->id_petugas, 'id_petugas'),
            ],
            'nomor_telepon' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:6'],
            'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $validated['status'] = strtoupper($validated['status']);
            if ($validated['status'] === 'NONAKTIF') {
                $hasActiveKelasMapel = \App\Models\DataKelasMapel::where('id_petugas', $petugas->id_petugas)
                    ->whereRaw('UPPER(status) = ?', ['AKTIF'])
                    ->exists();
                if ($hasActiveKelasMapel) {
                    return response()->json([
                        'message' => 'Tidak dapat menonaktifkan petugas karena masih aktif mengajar mata pelajaran kelas.',
                    ], 422);
                }
            }
        }

        if (!empty($validated['password'])) {
            $validated['password_hash'] = Hash::make($validated['password']);
        }

        unset($validated['password']);

        $petugas->update($validated);

        return response()->json([
            'message' => 'Data petugas berhasil diperbarui.',
            'data' => $petugas->fresh(),
        ]);
    }

    /**
     * Hapus data petugas.
     */
    public function destroy(int $id): JsonResponse
    {
        $petugas = DataPetugas::findOrFail($id);

        $hasKelasMapel = \App\Models\DataKelasMapel::where('id_petugas', $petugas->id_petugas)->exists();
        if ($hasKelasMapel) {
            return response()->json([
                'message' => 'Tidak dapat menghapus petugas karena masih terdaftar mengajar kelas.',
            ], 422);
        }

        $hasSesi = \App\Models\SesiAbsensi::where('id_petugas_hadir', $petugas->id_petugas)->exists();
        if ($hasSesi) {
            return response()->json([
                'message' => 'Tidak dapat menghapus petugas karena memiliki riwayat sesi absensi.',
            ], 422);
        }

        try {
            $petugas->delete();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Data petugas tidak dapat dihapus karena masih dipakai pada data lain.',
            ], 422);
        }

        return response()->json([
            'message' => 'Data petugas berhasil dihapus.',
        ]);
    }

    /**
     * Ambil opsi peran akun untuk form master data petugas.
     */
    public function peranAkunOptions(): JsonResponse
    {
        return response()->json([
            'data' => DataPetugas::PERAN_AKUN_OPTIONS,
        ]);
    }

    /**
     * Import data petugas dari CSV (upsert berdasarkan alamat_email).
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
        ]);

        $extension = strtolower((string) $request->file('file')->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $import = new DataPetugasImport();
            Excel::import($import, $request->file('file'));

            return response()->json([
                'message' => 'Import data petugas selesai.',
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

            $payload = $this->mapPetugasPayload($rowData);
            $payload = $this->normalizePetugasPayload($payload);

            $validator = Validator::make($payload, [
                'nomor_induk' => ['nullable', 'string', 'max:20'],
                'nama_lengkap' => ['required', 'string', 'max:200'],
                'peran_akun' => ['required', 'string', Rule::in(DataPetugas::PERAN_AKUN_OPTIONS)],
                'pilihan_unit' => ['nullable', 'string', 'max:10'],
                'alamat_email' => ['required', 'email', 'max:100'],
                'nomor_telepon' => ['nullable', 'string', 'max:20'],
                'password' => ['nullable', 'string', 'min:6'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = DataPetugas::where('alamat_email', $payload['alamat_email'])->first();

            if (!$existing && empty($payload['password'])) {
                $failed[] = [
                    'line' => $lineNumber,
                    'errors' => ['Password wajib diisi untuk data petugas baru.'],
                ];
                continue;
            }

            if (!empty($payload['nomor_induk'])) {
                $nomorIndukOwner = DataPetugas::where('nomor_induk', $payload['nomor_induk'])->first();

                if ($nomorIndukOwner && (!$existing || $nomorIndukOwner->id_petugas !== $existing->id_petugas)) {
                    $failed[] = [
                        'line' => $lineNumber,
                        'errors' => ['Nomor induk sudah digunakan oleh petugas lain.'],
                    ];
                    continue;
                }
            }

            $persistPayload = [
                'nomor_induk' => $payload['nomor_induk'],
                'nama_lengkap' => $payload['nama_lengkap'],
                'peran_akun' => $payload['peran_akun'],
                'pilihan_unit' => $payload['pilihan_unit'],
                'alamat_email' => $payload['alamat_email'],
                'nomor_telepon' => $payload['nomor_telepon'],
                'status' => strtoupper($payload['status'] ?? 'AKTIF'),
            ];

            if (!empty($payload['password'])) {
                $persistPayload['password_hash'] = Hash::make($payload['password']);
            }

            if ($existing) {
                $existing->update($persistPayload);
                $updated++;
                continue;
            }

            DataPetugas::create($persistPayload);
            $inserted++;
        }

        fclose($handle);

        return response()->json([
            'message' => 'Import data petugas selesai.',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'failed' => count($failed),
                'error_rows' => $failed,
            ],
        ]);
    }

    /**
     * Export data petugas ke CSV sesuai filter.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new DataPetugasExport(
                status: $request->filled('status') ? (string) $request->status : null,
                peranAkun: $request->filled('peran_akun') ? (string) $request->peran_akun : null,
                keyword: $request->filled('q') ? (string) $request->q : null,
            ),
            'data-petugas-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Template CSV import data petugas.
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = [
            'nomor_induk',
            'nama_lengkap',
            'peran_akun',
            'pilihan_unit',
            'alamat_email',
            'nomor_telepon',
            'password',
            'status',
        ];

        return response()->streamDownload(function () use ($headers) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            fclose($output);
        }, 'template-import-petugas.csv', [
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

    private function mapPetugasPayload(array $rowData): array
    {
        return [
            'nomor_induk' => $rowData['nomor_induk'] ?? null,
            'nama_lengkap' => $rowData['nama_lengkap'] ?? null,
            'peran_akun' => $rowData['peran_akun'] ?? null,
            'pilihan_unit' => $rowData['pilihan_unit'] ?? null,
            'alamat_email' => $rowData['alamat_email'] ?? null,
            'nomor_telepon' => $rowData['nomor_telepon'] ?? null,
            'password' => $rowData['password'] ?? null,
            'status' => $rowData['status'] ?? null,
        ];
    }

    private function normalizePetugasPayload(array $payload): array
    {
        foreach (['nomor_induk', 'nomor_telepon', 'password'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null) {
                $payload[$field] = trim((string) $payload[$field]);
                if ($payload[$field] === '') {
                    $payload[$field] = null;
                }
            }
        }

        if (!empty($payload['status'])) {
            $payload['status'] = strtoupper((string) $payload['status']);
        }

        return $payload;
    }
}
