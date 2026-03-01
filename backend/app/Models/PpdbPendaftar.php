<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbPendaftar extends Model
{
    protected $table = 'ppdb_pendaftar';
    protected $primaryKey = 'id_pendaftaran';

    protected $fillable = [
        'id_akun', 'no_pendaftaran', 'no_pendaftaran_final',
        'nama_calon', 'jenjang', 'nomor_umi', 'asal_kota',
        'is_luar_kota', 'status_verifikasi', 'tanggal_daftar',
    ];

    protected $casts = [
        'tanggal_daftar' => 'date',
        'is_luar_kota'   => 'boolean',
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
}
