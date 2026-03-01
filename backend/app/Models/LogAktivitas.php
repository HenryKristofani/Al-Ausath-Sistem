<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';
    protected $primaryKey = 'id_log';
    public $timestamps = false;

    protected $fillable = [
        'id_petugas', 'aksi', 'modul', 'deskripsi',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
