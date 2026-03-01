<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPembelajaran extends Model
{
    protected $table = 'jadwal_pembelajaran';
    protected $primaryKey = 'id_jadwal';

    protected $fillable = [
        'id_kelas_mapel', 'tahun_ajaran', 'hari',
        'jam_mulai', 'jam_selesai', 'ruangan', 'status',
    ];

    public function kelasMapel()
    {
        return $this->belongsTo(DataKelasMapel::class, 'id_kelas_mapel', 'id_kelas_mapel');
    }
}
