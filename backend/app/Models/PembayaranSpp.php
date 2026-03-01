<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranSpp extends Model
{
    protected $table = 'pembayaran_spp';
    protected $primaryKey = 'id_pembayaran';

    public $timestamps = false; // tabel ini tidak punya created_at/updated_at

    protected $fillable = [
        'id_santri', 'id_setting', 'nominal_bayar',
        'tanggal_bayar', 'metode_bayar', 'id_rekening', 'status',
    ];

    protected $casts = [
        'tanggal_bayar' => 'datetime',
        'nominal_bayar' => 'decimal:2',
    ];

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'id_santri', 'id_santri');
    }

    public function setting()
    {
        return $this->belongsTo(SppSetting::class, 'id_setting', 'id_setting');
    }

    public function rekening()
    {
        return $this->belongsTo(DataRekeningBank::class, 'id_rekening', 'id_rekening');
    }
}
