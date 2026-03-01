<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataKonversiNilai extends Model
{
    protected $table = 'data_konversi_nilai';
    protected $primaryKey = 'id_konversi';

    protected $fillable = [
        'nilai_min', 'nilai_max', 'predikat', 'keterangan', 'kode_unit',
    ];
}
