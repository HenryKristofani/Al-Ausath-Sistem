<?php

namespace App\Exports;

use App\Models\DataKelas;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DataKelasExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly ?string $kodeUnit,
        private readonly ?string $tahunAjaran,
        private readonly ?string $status,
        private readonly ?string $statusPpdb,
        private readonly ?string $keyword,
    ) {
    }

    public function collection(): Collection
    {
        return DataKelas::query()
            ->withCount([
                'santri as jumlah_santri',
                'santriAktif as jumlah_santri_aktif',
                'santriLulus as jumlah_santri_lulus',
                'santriKeluar as jumlah_santri_keluar',
            ])
            ->where('is_deleted', false)
            ->when(!empty($this->kodeUnit), fn ($q) => $q->where('kode_unit', strtoupper($this->kodeUnit)))
            ->when(!empty($this->tahunAjaran), fn ($q) => $q->where('tahun_ajaran', $this->tahunAjaran))
            ->when(!empty($this->status), fn ($q) => $q->where('status', strtoupper($this->status)))
            ->when(!empty($this->statusPpdb), fn ($q) => $q->where('status_ppdb', strtoupper($this->statusPpdb)))
            ->when(!empty($this->keyword), function ($q) {
                $keyword = $this->keyword;
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('nama_kelas', 'like', "%{$keyword}%")
                        ->orWhere('nama_jurusan', 'like', "%{$keyword}%");
                });
            })
            ->orderBy('kode_unit')
            ->orderBy('nama_kelas')
            ->get()
            ->map(fn (DataKelas $kelas) => [
                'kode_unit' => $kelas->kode_unit,
                'kode_kelas' => $kelas->kode_kelas,
                'nama_kelas' => $kelas->nama_kelas,
                'nama_jurusan' => $kelas->nama_jurusan,
                'tahun_ajaran' => $kelas->tahun_ajaran,
                'status' => $kelas->status,
                'status_ppdb' => $kelas->status_ppdb,
                'jumlah_santri' => $kelas->jumlah_santri,
                'jumlah_santri_aktif' => $kelas->jumlah_santri_aktif,
                'jumlah_santri_lulus' => $kelas->jumlah_santri_lulus,
                'jumlah_santri_keluar' => $kelas->jumlah_santri_keluar,
            ]);
    }

    public function headings(): array
    {
        return [
            'kode_unit',
            'kode_kelas',
            'nama_kelas',
            'nama_jurusan',
            'tahun_ajaran',
            'status',
            'status_ppdb',
            'jumlah_santri',
            'jumlah_santri_aktif',
            'jumlah_santri_lulus',
            'jumlah_santri_keluar',
        ];
    }
}
