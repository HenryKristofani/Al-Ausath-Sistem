<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRekeningBank extends Model
{
    protected $table = 'data_rekening_bank';
    protected $primaryKey = 'id_rekening';

    protected $fillable = [
        'nama_bank', 'nomor_rekening', 'nama_pemilik',
        'kode_unit', 'status',
    ];
}
