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
        'jenjang',
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
        'tanggal_pengumuman' => 'date',
        'tanggal_diterima' => 'date',
        'is_luar_kota' => 'boolean',
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

    public function notifikasi()
    {
        return $this->hasMany(PpdbNotifikasi::class, 'id_pendaftaran', 'id_pendaftaran');
    }

    public function santriDiterima()
    {
        return $this->belongsTo(DataSantri::class, 'id_santri', 'id_santri');
    }
}


