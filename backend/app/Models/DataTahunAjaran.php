<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataTahunAjaran extends Model
{
    protected $table = 'data_tahun_ajaran';
    protected $primaryKey = 'id_tahun_ajaran';

    protected $fillable = [
        'tahun_ajaran', 'semester', 'tanggal_mulai',
        'tanggal_selesai', 'status',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
    ];
}
