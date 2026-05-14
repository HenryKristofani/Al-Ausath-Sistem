<?php

namespace App\Observers;

use App\Models\DataNilaiSiswa;
use App\Models\DataRaport;
use Illuminate\Support\Facades\DB;

class DataNilaiSiswaObserver
{
    /**
     * Handle the DataNilaiSiswa "updated" event.
     * Auto-recalculate DRAFT raports when grades are updated.
     */
    public function updated(DataNilaiSiswa $nilai): void
    {
        $this->recalculateDraftRaports(
            nomorInduk: $nilai->nomor_induk,
            tahunAjaran: $nilai->tahun_ajaran,
            semester: $nilai->semester
        );
    }

    /**
     * Handle the DataNilaiSiswa "created" event.
     * Auto-recalculate DRAFT raports when grades are created.
     */
    public function created(DataNilaiSiswa $nilai): void
    {
        $this->recalculateDraftRaports(
            nomorInduk: $nilai->nomor_induk,
            tahunAjaran: $nilai->tahun_ajaran,
            semester: $nilai->semester
        );
    }

    /**
     * Recalculate rata_rata and jumlah_nilai for DRAFT raports.
     * Called when grades are updated or created.
     */
    private function recalculateDraftRaports(string $nomorInduk, string $tahunAjaran, int $semester): void
    {
        // Cek apakah ada raport dengan status DRAFT untuk santri ini
        $raport = DataRaport::query()
            ->where('nomor_induk', $nomorInduk)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->where('status_raport', 'DRAFT')
            ->first();

        if (! $raport) {
            return; // Tidak ada raport DRAFT, tidak perlu recalculate
        }

        // Ambil semua nilai mapel dengan nilai_rapor_tampil yang ter-set
        $nilaiRows = DB::table('data_nilai_siswa')
            ->where('nomor_induk', $nomorInduk)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->whereNotNull('nilai_rapor_tampil')
            ->get(['nilai_rapor_tampil']);

        // Hitung jumlah dan rata-rata baru
        $jumlahNilai = (float) $nilaiRows->sum(fn($row) => (float) $row->nilai_rapor_tampil);
        $rataRataMapel = $nilaiRows->count() > 0
            ? $this->roundHalfUp($jumlahNilai / $nilaiRows->count(), 2)
            : 0.0;

        // Update raport dengan nilai baru
        $raport->update([
            'jumlah_nilai' => $this->roundHalfUp($jumlahNilai, 2),
            'rata_rata' => $rataRataMapel,
        ]);
    }

    /**
     * Round half up helper (same as RaportGenerateController).
     */
    private function roundHalfUp(float $value, int $precision = 0): float
    {
        $multiplier = 10 ** $precision;
        return (float) ceil($value * $multiplier - 0.5) / $multiplier;
    }
}
