<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SppGolongan extends Model
{
    protected $table = 'spp_golongan';
    protected $primaryKey = 'id_golongan';

    protected $fillable = [
        'nama_golongan',
        'jenjang',
        'nominal',
        'keterangan',
        'is_aktif',
    ];

    protected $casts = [
        'nominal' => 'float',
        'is_aktif' => 'boolean',
    ];

    public function santri()
    {
        return $this->hasMany(DataSantri::class, 'id_golongan_spp', 'id_golongan');
    }

    public function settingSpp()
    {
        return $this->hasMany(SppSetting::class, 'id_golongan_spp', 'id_golongan');
    }
}
