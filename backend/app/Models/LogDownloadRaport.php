<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogDownloadRaport extends Model
{
    protected $table = 'log_download_raport';
    protected $primaryKey = 'id_log_download';
    public $timestamps = false;

    protected $fillable = [
        'id_raport', 'id_pengguna', 'tipe_pengguna',
        'waktu_download', 'ip_address',
    ];

    protected $casts = [
        'waktu_download' => 'datetime',
    ];
}
