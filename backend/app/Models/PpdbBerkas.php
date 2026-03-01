<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbBerkas extends Model
{
    protected $table = 'ppdb_berkas';
    protected $primaryKey = 'id_berkas';

    protected $fillable = [
        'id_pendaftaran', 'jenis_berkas', 'nama_file',
        'path_file', 'status_verifikasi', 'keterangan',
    ];

    public function pendaftar()
    {
        return $this->belongsTo(PpdbPendaftar::class, 'id_pendaftaran', 'id_pendaftaran');
    }
}
