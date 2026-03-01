<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataNilaiSiswa extends Model
{
    protected $table = 'data_nilai_siswa';
    protected $primaryKey = 'id_nilai';

    protected $fillable = [
        'id_santri', 'id_kelas_mapel', 'jenis_penilaian',
        'nilai', 'semester', 'tahun_ajaran', 'keterangan',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
    ];
}
