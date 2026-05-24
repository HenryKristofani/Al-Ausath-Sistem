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
        if (! Schema::hasTable('absensi_pengajar')) {
            Schema::create('absensi_pengajar', function (Blueprint $table) {
                $table->integer('id_abs_pengajar', true)->primary();
                $table->integer('id_petugas');
                $table->integer('id_sesi')->nullable();
                $table->date('tanggal');
                $table->string('status_kehadiran', 20);
                $table->integer('menit_terlambat')->default(0);
                $table->text('keterangan')->nullable();
                $table->integer('input_oleh')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
                $table->foreign('id_sesi')->references('id_sesi')->on('sesi_absensi');
                $table->foreign('input_oleh')->references('id_petugas')->on('data_petugas');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_pengajar');
    }
};