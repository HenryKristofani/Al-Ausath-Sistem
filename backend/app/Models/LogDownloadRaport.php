<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogDownloadRaport extends Model
{
    protected $table = 'log_download_raport';
    protected $primaryKey = 'id_log';

    const UPDATED_AT = null;
    public $timestamps = false;

        protected $fillable = [
        'id_raport',
        'nomor_induk',
        'id_petugas',
        'tipe_pengunduh',
        'aksi',
        'nama_file_pdf',
        'ip_address',
        'user_agent',
        'status_aksi',
        'keterangan',
    ];
}


