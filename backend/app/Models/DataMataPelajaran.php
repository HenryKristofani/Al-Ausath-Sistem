<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataMataPelajaran extends Model
{
    protected $table = 'data_mata_pelajaran';
    protected $primaryKey = 'id_mapel';

        protected $fillable = [
        'kode_mapel',
        'nama_mapel',
        'kode_unit',
        'kelompok_mapel',
        'urutan',
        'keterangan',
        'status',
    ];
}


