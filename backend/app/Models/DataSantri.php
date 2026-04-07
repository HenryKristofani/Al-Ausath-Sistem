<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataSantri extends Model
{
    protected $table = 'data_santri';
    protected $primaryKey = 'id_santri';

        protected $fillable = [
        'nomor_induk',
        'nama_lengkap_santri',
        'kode_kelas',
        'status',
        'tahun_masuk',
        'tahun_lulus',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'agama',
        'berat_badan',
        'tinggi_badan',
        'gol_darah',
        'provinsi',
        'kota_kabupaten',
        'kecamatan',
        'kelurahan',
        'alamat_tinggal',
        'nomor_telepon',
        'alamat_email',
        'nama_ayah_kandung',
        'nama_ibu_kandung',
        'nama_wali',
    ];

        protected $casts = [
        'tanggal_lahir' => 'date',
    ];

    // Relasi ke DataKelas
    public function kelas()
    {
        return $this->belongsTo(DataKelas::class, 'kode_kelas', 'kode_kelas');
    }

    // Relasi ke DataAkunSantri
    public function akun()
    {
        return $this->hasOne(DataAkunSantri::class, 'nomor_induk', 'nomor_induk');
    }

    public function pembayaranSpp()
    {
        return $this->hasMany(PembayaranSpp::class, 'id_santri', 'id_santri');
    }

    public function administrasiBebas()
    {
        return $this->hasMany(AdministrasiBebas::class, 'id_santri', 'id_santri');
    }

    public function sppSettingKhusus()
    {
        return $this->hasMany(SppSetting::class, 'id_santri', 'id_santri');
    }
}


