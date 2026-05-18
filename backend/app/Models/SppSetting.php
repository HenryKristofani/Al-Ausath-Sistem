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
        'id_santri',
        'id_golongan_spp',
        'kode_kelas',
        'jenjang',
        'kategori_tagihan_id',
        'jumlah',
        'periode',
        'keterangan',
        'aktif', // Add this to fillables
    ];

    protected $casts = [
        'jumlah' => 'decimal:2',
        'aktif' => 'boolean',  // Add this cast
    ];

    public function unit()
    {
        return $this->belongsTo(DataUnit::class, 'id_unit', 'id_unit');
    }

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'id_santri', 'id_santri');
    }

    public function kategoriTagihan()
    {
        return $this->belongsTo(DataKategoriTagihan::class, 'kategori_tagihan_id', 'id_kategori');
    }

    public function kelas()
    {
        return $this->belongsTo(DataKelas::class, 'kode_kelas', 'kode_kelas');
    }

    public function pembayaran()
    {
        return $this->hasMany(PembayaranSpp::class, 'id_setting', 'id_setting');
    }

    public function golonganSpp()
    {
        return $this->belongsTo(SppGolongan::class, 'id_golongan_spp', 'id_golongan');
    }
}


