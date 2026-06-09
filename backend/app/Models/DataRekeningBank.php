<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRekeningBank extends Model
{
    protected $table = 'data_rekening_bank';
    protected $primaryKey = 'id_rekening';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'nama_rekening',
        'nama_pemilik',
        'nomor_rekening',
        'nama_bank',
        'cabang_bank',
        'peruntukan',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeAktif($query)
    {
        return $query->where('status', 'AKTIF');
    }
}
