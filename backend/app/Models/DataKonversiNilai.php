<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKonversiNilai extends Model
{
    protected $table = 'data_konversi_nilai';
    protected $primaryKey = 'id_konversi';

    const UPDATED_AT = null;

        protected $fillable = [
        'kode_unit',
        'nilai_min',
        'nilai_max',
        'nilai_huruf',
        'predikat',
        'status',
    ];
}


