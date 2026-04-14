<?php

namespace App\Exports;

use App\Models\DataUnit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataUnitExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $status,
        private readonly ?string $statusPpdb,
        private readonly ?string $keyword,
    ) {
    }

    public function collection(): Collection
    {
        return DataUnit::query()
            ->withCount([
                'kelas as jumlah_kelas',
                'santri as jumlah_santri',
            ])
            ->when(!empty($this->status), fn ($q) => $q->where('status', strtoupper($this->status)))
            ->when(!empty($this->statusPpdb), fn ($q) => $q->where('status_ppdb', strtoupper($this->statusPpdb)))
            ->when(!empty($this->keyword), function ($q) {
                $keyword = $this->keyword;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_unit', 'like', "%{$keyword}%")
                        ->orWhere('nama_unit', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nomor_urut')
            ->orderBy('nama_unit')
            ->get()
            ->map(fn (DataUnit $unit) => [
                'kode_unit' => $unit->kode_unit,
                'nama_unit' => $unit->nama_unit,
                'nomor_urut' => $unit->nomor_urut,
                'keterangan' => $unit->keterangan,
                'status' => $unit->status,
                'status_ppdb' => $unit->status_ppdb,
                'jumlah_kelas' => $unit->jumlah_kelas,
                'jumlah_santri' => $unit->jumlah_santri,
            ]);
    }

    public function headings(): array
    {
        return [
            'kode_unit',
            'nama_unit',
            'nomor_urut',
            'keterangan',
            'status',
            'status_ppdb',
            'jumlah_kelas',
            'jumlah_santri',
        ];
    }
}
