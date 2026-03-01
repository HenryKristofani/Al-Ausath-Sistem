<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbsensiSantri extends Model
{
    protected $table = 'absensi_santri';
    protected $primaryKey = 'id_absensi';

    protected $fillable = [
        'id_sesi', 'nomor_induk', 'status_kehadiran',
        'keterangan', 'timestamp_input', 'input_oleh',
    ];

    protected $casts = [
        'timestamp_input' => 'datetime',
    ];

    public function santri()
    {
        return $this->belongsTo(DataSantri::class, 'nomor_induk', 'nomor_induk');
    }

    public function sesi()
    {
        return $this->belongsTo(SesiAbsensi::class, 'id_sesi', 'id_sesi');
    }
}
