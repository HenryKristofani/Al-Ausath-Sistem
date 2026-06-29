<?php

namespace App\Exports;

use App\Models\DataMataPelajaran;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataMataPelajaranExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $kodeUnit,
        private readonly ?string $kelompokMapel,
        private readonly ?string $status,
        private readonly ?string $keyword,
    ) {
    }

    public function collection(): Collection
    {
        return DataMataPelajaran::query()
            ->when(!empty($this->kodeUnit), fn ($q) => $q->where('kode_unit', strtoupper($this->kodeUnit)))
            ->when(!empty($this->kelompokMapel), fn ($q) => $q->where('kelompok_mapel', $this->kelompokMapel))
            ->when(!empty($this->status), fn ($q) => $q->where('status', strtoupper($this->status)))
            ->when(!empty($this->keyword), function ($q) {
                $keyword = $this->keyword;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_mapel', 'like', "%{$keyword}%")
                        ->orWhere('nama_mapel', 'like', "%{$keyword}%")
                        ->orWhere('kelompok_mapel', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('nama_mapel')
            ->get([
                'kode_mapel',
                'nama_mapel',
                'kode_unit',
                'kelompok_mapel',
                'keterangan',
                'status',
            ]);
    }

    public function headings(): array
    {
        return [
            'kode_mapel',
            'nama_mapel',
            'kode_unit',
            'kelompok_mapel',
            'keterangan',
            'status',
        ];
    }
}
