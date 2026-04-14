<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataUnit extends Model
{
    protected $table = 'data_unit';
    protected $primaryKey = 'id_unit';

        protected $fillable = [
        'kode_unit',
        'nama_unit',
        'nomor_urut',
        'keterangan',
        'status',
        'status_ppdb',
    ];

    public function kelas()
    {
        return $this->hasMany(DataKelas::class, 'kode_unit', 'kode_unit');
    }

    public function santri()
    {
        return $this->hasManyThrough(
            DataSantri::class,
            DataKelas::class,
            'kode_unit',
            'kode_kelas',
            'kode_unit',
            'kode_kelas'
        );
    }
}


