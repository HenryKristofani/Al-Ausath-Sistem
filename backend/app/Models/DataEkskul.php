<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataEkskul extends Model
{
    protected $table = 'data_ekskul';
    protected $primaryKey = 'id_ekskul';

    protected $fillable = [
        'kode_unit',
        'nama_ekskul',
        'deskripsi',
        'kuota',
        'status',
        'status_pendaftaran',
    ];

    protected $casts = [
        'kuota' => 'integer',
    ];

    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'kode_unit', 'kode_unit');
    }

    public function pendaftaran()
    {
        return $this->hasMany(PendaftaranEkskul::class, 'id_ekskul', 'id_ekskul');
    }
}
