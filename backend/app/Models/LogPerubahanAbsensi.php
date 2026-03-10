<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogPerubahanAbsensi extends Model
{
    protected $table = 'log_perubahan_absensi';
    protected $primaryKey = 'id_log';

    public $timestamps = false;
    public $timestamps = false;

        protected $fillable = [
        'tabel_terkait',
        'id_record',
        'field_diubah',
        'nilai_lama',
        'nilai_baru',
        'alasan_perubahan',
        'diubah_oleh',
        'diubah_pada',
        'ip_address',
    ];
}


