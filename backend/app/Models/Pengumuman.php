<?php

namespace App\Models;

use App\Models\PengumumanLampiran;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengumuman extends Model
{
    protected $table = 'pengumuman';

    protected $fillable = [
        'id_unit',
        'judul',
        'konten',
        'lampiran_path',
        'lampiran_nama_asli',
        'lampiran_mime',
        'lampiran_size',
        'kategori',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_aktif',
        'is_pinned',
        'urutan',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'lampiran_size'   => 'integer',
        'is_aktif'        => 'boolean',
        'is_pinned'       => 'boolean',
        'urutan'          => 'integer',
    ];

    public function lampirans(): HasMany
    {
        return $this->hasMany(PengumumanLampiran::class, 'pengumuman_id');
    }

    public function latestLampiran(): ?PengumumanLampiran
    {
        if ($this->relationLoaded('lampirans')) {
            return $this->lampirans->sortByDesc('created_at')->first();
        }

        return $this->lampirans()->latest()->first();
    }

    public function unit()
    {
        return $this->belongsTo(\App\Models\DataUnit::class, 'id_unit');
    }
}
