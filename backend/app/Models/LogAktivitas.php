<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';
    protected $primaryKey = 'id_log_aktivitas';

    const UPDATED_AT = null;
    public $timestamps = false;

        protected $fillable = [
        'id_petugas',
        'jenis_aksi',
        'modul',
        'deskripsi',
        'ip_address',
        'user_agent',
    ];

        protected $casts = [
        'created_at' => 'datetime',
    ];
}


