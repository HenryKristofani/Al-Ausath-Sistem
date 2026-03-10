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
        Schema::create('ppdb_pendaftar', function (Blueprint $table) {
        $table->integer('id_pendaftaran', true)->primary();
            $table->integer('id_akun')->nullable();
            $table->string('no_pendaftaran', 50)->unique();
            $table->string('no_pendaftaran_final', 50)->nullable();
            $table->string('nama_calon', 200);
            $table->string('jenjang', 20)->nullable();
            $table->string('nomor_umi', 50)->nullable();
            $table->string('asal_kota', 100)->nullable();
            $table->boolean('is_luar_kota')->default(false);
            $table->string('status_verifikasi', 30)->default('pending');
            $table->date('tanggal_daftar')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('id_akun');
            $table->index('no_pendaftaran');
            $table->index('status_verifikasi');
            $table->foreign('id_akun')->references('id_akun')->on('akun_pendaftar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_pendaftar');
    }
};