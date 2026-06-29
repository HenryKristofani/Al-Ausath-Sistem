<?php

namespace App\Imports;

use App\Models\DataUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DataUnitImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private int $inserted = 0;

    private int $updated = 0;

    /**
     * @var array<int, array{line:int,errors:array<int,string>}>
     */
    private array $failed = [];

    /**
     * @var array<int, string>
     */
    private array $affectedKodeUnit = [];

    public function collection(Collection $rows): void
    {
        $lineNumber = 1;

        foreach ($rows as $row) {
            $lineNumber++;

            $payload = [
                'kode_unit' => $this->rowValue($row, 'kode_unit'),
                'nama_unit' => $this->rowValue($row, 'nama_unit'),
                'keterangan' => $this->rowValue($row, 'keterangan'),
                'status' => $this->rowValue($row, 'status'),
            ];

            if ($this->isEmptyRow($payload)) {
                continue;
            }

            $validator = Validator::make($payload, [
                'kode_unit' => ['required', 'string', 'max:10'],
                'nama_unit' => ['required', 'string', 'max:100'],
                'keterangan' => ['nullable', 'string'],
                'status' => ['nullable', 'string', Rule::in(['AKTIF', 'NONAKTIF'])],
            ]);

            if ($validator->fails()) {
                $this->failed[] = [
                    'line' => $lineNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            $payload['kode_unit'] = strtoupper((string) $payload['kode_unit']);

            if (!empty($payload['status'])) {
                $payload['status'] = strtoupper((string) $payload['status']);
            }

            $existing = DataUnit::where('kode_unit', (string) $payload['kode_unit'])->first();

            if ($existing) {
                $existing->update($payload);
                $this->markAffectedKodeUnit((string) $payload['kode_unit']);
                $this->updated++;
                continue;
            }

            DataUnit::create($payload);
            $this->markAffectedKodeUnit((string) $payload['kode_unit']);
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

    /**
     * @return array<int, string>
     */
    public function affectedKodeUnit(): array
    {
        return array_values($this->affectedKodeUnit);
    }

    private function rowValue(mixed $row, string $key): mixed
    {
        $value = data_get($row, $key);

        if (is_string($value)) {
            $value = trim($value);
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

    private function markAffectedKodeUnit(string $kodeUnit): void
    {
        $kodeUnit = trim($kodeUnit);

        if ($kodeUnit === '') {
            return;
        }

        $this->affectedKodeUnit[$kodeUnit] = $kodeUnit;
    }
}
