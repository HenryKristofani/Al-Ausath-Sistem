<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_petugas', function (Blueprint $table) {
        $table->integer('id_petugas', true)->primary();
            $table->string('nomor_induk', 20)->nullable();
            $table->string('nama_lengkap', 200);
            $table->string('peran_akun', 50);
            $table->string('pilihan_unit', 10)->nullable();
            $table->string('alamat_email', 100)->unique();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('last_login')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('alamat_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_petugas');
    }
};