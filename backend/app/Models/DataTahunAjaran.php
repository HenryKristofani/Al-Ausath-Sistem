<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataTahunAjaran extends Model
{
    protected $table = 'data_tahun_ajaran';
    protected $primaryKey = 'id_tahun_ajaran';

        protected $fillable = [
        'kode_tahun',
        'nama_tahun',
        'keterangan',
        'status',
        'is_deleted',
        'deleted_at',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function kelas()
    {
        return $this->hasMany(DataKelas::class, 'tahun_ajaran', 'kode_tahun');
    }

    public function santri()
    {
        return $this->hasManyThrough(
            DataSantri::class,
            DataKelas::class,
            'tahun_ajaran',
            'kode_kelas',
            'kode_tahun',
            'kode_kelas'
        );
    }
}


