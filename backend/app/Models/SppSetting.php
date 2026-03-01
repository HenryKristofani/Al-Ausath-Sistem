<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppSetting extends Model
{
    protected $table = 'spp_setting';
    protected $primaryKey = 'id_setting';

    protected $fillable = [
        'kode_unit', 'nama_tagihan', 'nominal', 'bulan',
        'tahun_ajaran', 'status',
    ];

    protected $casts = [
        'nominal' => 'decimal:2',
    ];
}
