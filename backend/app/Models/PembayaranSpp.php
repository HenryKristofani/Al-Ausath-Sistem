<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PembayaranSpp extends Model
{
    protected $table = 'pembayaran_spp';
    protected $primaryKey = 'id_pembayaran';

    public $timestamps = false;

        protected $fillable = [
        'id_pendaftaran',
        'id_santri',
        'id_setting',
        'nominal_bayar',
        'tanggal_bayar',
        'metode_bayar',
        'id_rekening',
        'status',
        'tanggal_verifikasi',
        'id_petugas_verifikator',
    ];

        protected $casts = [
        'tanggal_bayar' => 'datetime',
        'tanggal_verifikasi' => 'datetime',
        'nominal_bayar' => 'decimal:2',
    ];

    public function pendaftarPpdb()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }

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

    public function kwitansi()
    {
        return $this->hasOne(KwitansiPdf::class, 'id_pembayaran', 'id_pembayaran');
    }
}


