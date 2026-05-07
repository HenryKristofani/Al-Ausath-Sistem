<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengumumanLampiran extends Model
{
    protected $table = 'pengumuman_lampiran';

    protected $fillable = [
        'pengumuman_id',
        'path',
        'nama_asli',
        'mime',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function pengumuman(): BelongsTo
    {
        return $this->belongsTo(Pengumuman::class, 'pengumuman_id');
    }
}
