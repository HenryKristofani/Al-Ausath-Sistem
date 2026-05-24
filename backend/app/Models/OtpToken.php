<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OtpToken extends Model
{
    protected $table = 'otp_tokens';

    protected $fillable = [
        'identifier',
        'guard',
        'kode',
        'sudah_digunakan',
        'kadaluarsa_at',
    ];

    protected $casts = [
        'sudah_digunakan' => 'boolean',
        'kadaluarsa_at'   => 'datetime',
    ];

    public function isValid(): bool
    {
        return !$this->sudah_digunakan && $this->kadaluarsa_at->isFuture();
    }
}
