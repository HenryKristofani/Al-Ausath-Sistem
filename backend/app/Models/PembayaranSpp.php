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
        'bulan',
        'nominal_bayar',
        'tanggal_bayar',
        'metode_bayar',
        'status',
        'bukti_bayar_path',
        'catatan_bayar',
        'tanggal_verifikasi',
        'tanggal_konfirmasi',
        'id_petugas_verifikator',
    ];

    protected $casts = [
        'tanggal_bayar' => 'datetime',
        'tanggal_verifikasi' => 'datetime',
        'tanggal_konfirmasi' => 'datetime',
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

    public function kwitansi()
    {
        return $this->hasOne(KwitansiPdf::class, 'id_pembayaran', 'id_pembayaran');
    }
}


