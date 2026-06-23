<?php

namespace App\Imports;

use App\Models\DataPetugas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DataPetugasImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var array<int, array{line:int,errors:array<int,string>}>
     */
    private array $failed = [];

    public function collection(Collection $rows): void
    {
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            $payload = [
                'nomor_induk' => $this->rowValue($row, 'nomor_induk'),
                'nama_lengkap' => $this->rowValue($row, 'nama_lengkap'),
                'peran_akun' => $this->rowValue($row, 'peran_akun'),
                'alamat_email' => $this->rowValue($row, 'alamat_email'),
                'nomor_telepon' => $this->rowValue($row, 'nomor_telepon'),
                'password' => $this->rowValue($row, 'password'),
                'status' => $this->rowValue($row, 'status'),
            ];

            $payload = $this->normalizePayload($payload);

            if ($this->isEmptyRow($payload)) {
                continue;
            }

            $validator = Validator::make($payload, [
                'nomor_induk' => ['nullable', 'string', 'max:20'],
                'nama_lengkap' => ['required', 'string', 'max:200'],
                'peran_akun' => ['required', 'array', 'min:1'],
                'peran_akun.*' => ['required', 'string', Rule::in(DataPetugas::PERAN_AKUN_OPTIONS)],
                'alamat_email' => ['required', 'email', 'max:100'],
                'nomor_telepon' => ['nullable', 'string', 'max:20'],
                'password' => ['nullable', 'string', 'min:6'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $this->failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $existing = DataPetugas::where('alamat_email', (string) $payload['alamat_email'])->first();

            if (!$existing && empty($payload['password'])) {
                $this->failed[] = [
                    'line' => $lineNumber,
                    'errors' => ['Password wajib diisi untuk data petugas baru.'],
                ];
                continue;
            }

            if (!empty($payload['nomor_induk'])) {
                $nomorIndukOwner = DataPetugas::where('nomor_induk', (string) $payload['nomor_induk'])->first();

                if ($nomorIndukOwner && (!$existing || $nomorIndukOwner->id_petugas !== $existing->id_petugas)) {
                    $this->failed[] = [
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
                'alamat_email' => $payload['alamat_email'],
                'nomor_telepon' => $payload['nomor_telepon'],
                'status' => strtoupper((string) ($payload['status'] ?? 'AKTIF')),
            ];

            if (!empty($payload['password'])) {
                $persistPayload['password_hash'] = Hash::make((string) $payload['password']);
            }

            if ($existing) {
                $existing->update($persistPayload);
                $this->updated++;
                continue;
            }

            DataPetugas::create($persistPayload);
            $this->inserted++;
        }
    }

    /**
     * @return array{inserted:int,updated:int,failed:int,error_rows:array<int,array{line:int,errors:array<int,string>}>}
     */
    public function result(): array
    {
        return [
            'inserted' => $this->inserted,
            'updated' => $this->updated,
            'failed' => count($this->failed),
            'error_rows' => $this->failed,
        ];
    }

    private function rowValue(mixed $row, string $key): mixed
    {
        $value = data_get($row, $key);

        if (is_scalar($value)) {
            $value = trim((string) $value);
            return $value === '' ? null : $value;
        }

        return $value;
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

    private function normalizePayload(array $payload): array
    {
        foreach (['nomor_induk', 'nomor_telepon', 'password'] as $field) {
            if ($payload[$field] !== null) {
                $payload[$field] = trim((string) $payload[$field]);
                if ($payload[$field] === '') {
                    $payload[$field] = null;
                }
            }
        }

        if (!empty($payload['status'])) {
            $payload['status'] = strtoupper((string) $payload['status']);
        }

        if (array_key_exists('peran_akun', $payload) && is_string($payload['peran_akun'])) {
            $roles = array_map('trim', explode(',', $payload['peran_akun']));
            $payload['peran_akun'] = array_filter($roles, fn($role) => $role !== '');
        }

        return $payload;
    }
}
