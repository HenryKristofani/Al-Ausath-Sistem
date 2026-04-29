<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbPendaftar extends Model
{
    protected $table = 'ppdb_pendaftar';
    protected $primaryKey = 'id_pendaftaran';

    const UPDATED_AT = null;

        protected $fillable = [
        'id_akun',
        'id_santri',
        'no_pendaftaran',
        'no_pendaftaran_final',
        'nomor_induk_generated',
        'nama_calon',
        'program_pendaftaran',
        'jenjang',
        'jenis_kelamin',
        'tempat_lahir',
        'tanggal_lahir',
        'nik_calon_santri',
        'alamat_lengkap',
        'riwayat_penyakit',
        'nama_ayah',
        'penghasilan_ayah',
        'no_hp_calon',
        'nama_ibu',
        'no_hp_ibu',
        'soal_jawab',
        'file_akta_path',
        'file_kk_path',
        'file_surat_rekomendasi_path',
        'surat_pernyataan_setuju',
        'surat_pernyataan_file_path',
        'waktu_pendaftaran',
        'nomor_umi',
        'asal_kota',
        'kode_kelas_diterima',
        'is_luar_kota',
        'status_verifikasi',
        'tanggal_daftar',
        'tanggal_pengumuman',
        'tanggal_diterima',
    ];

        protected $casts = [
        'tanggal_daftar' => 'date',
        'tanggal_lahir' => 'date',
        'tanggal_pengumuman' => 'date',
        'tanggal_diterima' => 'date',
        'waktu_pendaftaran' => 'datetime',
        'is_luar_kota' => 'boolean',
        'surat_pernyataan_setuju' => 'boolean',
    ];

    public function akun()
    {
        return $this->belongsTo(AkunPendaftar::class, 'id_akun', 'id_akun');
    }

    public function berkas()
    {
        return $this->hasMany(PpdbBerkas::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function verifikasi()
    {
        return $this->hasOne(PpdbVerifikasi::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function tes()
    {
        return $this->hasOne(PpdbTes::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function ppdbTes()
    {
        return $this->hasOne(PpdbTes::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function notifikasi()
    {
        return $this->hasMany(PpdbNotifikasi::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function santriDiterima()
    {
        return $this->belongsTo(DataSantri::class, 'id_santri', 'id_santri');
    }
}


