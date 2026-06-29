<?php

namespace App\Exports;

use App\Models\JadwalPembelajaran;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class DataJadwalPembelajaranExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping
{
    public function __construct(
        private readonly ?int $idKelasMapel,
        private readonly ?string $tahunAjaran,
        private readonly ?string $hari,
        private readonly ?string $status,
        private readonly ?string $keyword,
    ) {
    }

    public function collection(): Collection
    {
        return JadwalPembelajaran::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->when($this->idKelasMapel !== null, fn ($q) => $q->where('id_kelas_mapel', $this->idKelasMapel))
            ->when(!empty($this->tahunAjaran), fn ($q) => $q->where('tahun_ajaran', trim((string) $this->tahunAjaran)))
            ->when(!empty($this->hari), fn ($q) => $q->where('hari', strtoupper(trim((string) $this->hari))))
            ->when(!empty($this->status), fn ($q) => $q->where('status', strtoupper(trim((string) $this->status))))
            ->when(!empty($this->keyword), function ($q) {
                $keyword = $this->keyword;
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
            ->orderBy('jam_mulai')
            ->get();
    }

    public function map($row): array
    {
        return [
            $row->kelasMapel->kelas->nama_kelas ?? '-',
            $row->kelasMapel->mataPelajaran->nama_mapel ?? '-',
            $row->hari,
            $row->jam_mulai,
            $row->jam_selesai,
            ($row->kelasMapel->tahun_ajaran ?? '-') . '/' . ($row->kelasMapel->semester ?? '-'),
            $row->ruangan,
            $row->status,
        ];
    }

    public function headings(): array
    {
        return [
            'KELAS',
            'MAPEL',
            'HARI',
            'JAM MULAI',
            'JAM SELESAI',
            'TAHUN/SEM',
            'RUANG',
            'STATUS',
        ];
    }
}
