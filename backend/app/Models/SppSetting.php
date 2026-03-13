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

        protected $casts = [
        'jumlah' => 'decimal:2',
    ];

    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'id_unit', 'id_unit');
    }

    public function kategoriTagihan()
    {
        return $this->belongsTo(DataKategoriTagihan::class, 'kategori_tagihan_id', 'id_kategori');
    }

    public function pembayaran()
    {
        return $this->hasMany(PembayaranSpp::class, 'id_setting', 'id_setting');
    }
}


