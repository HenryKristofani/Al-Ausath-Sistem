<?php

namespace App\Models;

use App\Observers\DataNilaiSiswaObserver;
use Illuminate\Database\Eloquent\Model;

class DataNilaiSiswa extends Model
{
    protected $table = 'data_nilai_siswa';
    protected $primaryKey = 'id_nilai';

    protected $fillable = [
        'nomor_induk',
        'kode_mapel',
        'kode_kelas',
        'tahun_ajaran',
        'semester',
        'nilai_harian',
        'nilai_uts',
        'nilai_uas',
        'nilai_akhir_mapel',
        'nilai_rapor_tampil',
        'flag_warna_rapor',
        'status_ketuntasan',
        'keterangan',
        'nilai_detail',
        'id_petugas_input',
    ];

    /**
     * Boot method to register observer.
     */
    protected static function boot(): void
    {
        parent::boot();
        static::observe(DataNilaiSiswaObserver::class);
    }
}
