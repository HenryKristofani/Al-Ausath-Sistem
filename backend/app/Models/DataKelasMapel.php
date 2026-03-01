<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKelasMapel extends Model
{
    protected $table = 'data_kelas_mapel';
    protected $primaryKey = 'id_kelas_mapel';

    protected $fillable = [
        'kode_kelas', 'id_mapel', 'id_pengajar', 'tahun_ajaran', 'status',
    ];

    public function kelas()
    {
        return $this->belongsTo(DataKelas::class, 'kode_kelas', 'kode_kelas');
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(DataMataPelajaran::class, 'id_mapel', 'id_mapel');
    }
}
