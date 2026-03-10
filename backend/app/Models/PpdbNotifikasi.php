<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PpdbNotifikasi extends Model
{
    protected $table = 'ppdb_notifikasi';
    protected $primaryKey = 'id_notif';

    public $timestamps = false;

        protected $fillable = [
        'id_pendaftaran',
        'type',
        'konten',
        'sent_at',
        'status_kirim',
    ];
}


