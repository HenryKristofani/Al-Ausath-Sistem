<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRaport extends Model
{
    protected $table = 'data_raport';
    protected $primaryKey = 'id_raport';

    protected $fillable = [
        'id_santri', 'semester', 'tahun_ajaran',
        'nilai_rata_rata', 'peringkat', 'keterangan', 'status',
    ];
}
