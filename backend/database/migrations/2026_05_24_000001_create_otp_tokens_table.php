<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 150)->comment('Email atau nomor telepon pengguna');
            $table->string('guard', 30)->default('petugas')->comment('petugas | santri | ppdb');
            $table->string('kode', 10);
            $table->boolean('sudah_digunakan')->default(false);
            $table->timestamp('kadaluarsa_at');
            $table->timestamps();

            $table->index(['identifier', 'guard']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_tokens');
    }
};
