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
        Schema::create('ppdb_verifikasi', function (Blueprint $table) {
        $table->integer('id_verif', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->integer('id_petugas')->nullable();
            $table->timestamp('tanggal_verif')->useCurrent();
            $table->string('hasil', 20)->nullable();
            $table->text('catatan')->nullable();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_verifikasi');
    }
};