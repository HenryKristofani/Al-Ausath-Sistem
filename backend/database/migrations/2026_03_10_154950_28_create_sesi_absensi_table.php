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
        Schema::create('sesi_absensi', function (Blueprint $table) {
        $table->integer('id_sesi', true)->primary();
            $table->integer('id_jadwal');
            $table->integer('id_petugas_hadir');
            $table->integer('id_petugas_pengganti')->nullable();
            $table->date('tanggal');
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->string('status_sesi', 20)->default('BERLANGSUNG');
            $table->text('keterangan')->nullable();
            $table->boolean('is_validated')->default(false);
            $table->integer('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('id_jadwal')->references('id_jadwal')->on('jadwal_pembelajaran');
            $table->foreign('id_petugas_hadir')->references('id_petugas')->on('data_petugas');
            $table->foreign('id_petugas_pengganti')->references('id_petugas')->on('data_petugas');
            $table->foreign('validated_by')->references('id_petugas')->on('data_petugas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sesi_absensi');
    }
};