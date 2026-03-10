<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKelas extends Model
{
    protected $table = 'data_kelas';
    protected $primaryKey = 'id_kelas';

        protected $fillable = [
        'kode_unit',
        'kode_kelas',
        'nama_kelas',
        'nama_jurusan',
        'tahun_ajaran',
        'status',
        'status_ppdb',
    ];

    // Relasi ke DataUnit
    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'kode_unit', 'kode_unit');
    }

    // Relasi ke DataSantri
    public function santri()
    {
        return $this->hasMany(DataSantri::class, 'kode_kelas', 'kode_kelas');
    }
}


