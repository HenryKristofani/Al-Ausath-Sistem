<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppSetting extends Model
{
    protected $table = 'spp_setting';
    protected $primaryKey = 'id_setting';

    public $timestamps = false;

        protected $fillable = [
        'id_unit',
        'jenjang',
        'kategori_tagihan_id',
        'jumlah',
        'periode',
        'keterangan',
    ];
}


