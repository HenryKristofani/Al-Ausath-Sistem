<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRekeningBank extends Model
{
    protected $table = 'data_rekening_bank';
    protected $primaryKey = 'id_rekening';

        protected $fillable = [
        'kode_unit',
        'kode_rekening',
        'nama_rekening',
        'nama_pemilik',
        'nomor_rekening',
        'nama_bank',
        'cabang_bank',
        'logo_bank',
        'peruntukan',
        'status',
        'is_connect',
    ];
}


