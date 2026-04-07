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
        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->unique(['id_jadwal', 'tanggal'], 'sesi_absensi_id_jadwal_tanggal_unique');
        });

        Schema::table('absensi_santri', function (Blueprint $table) {
            $table->unique(['id_sesi', 'nomor_induk'], 'absensi_santri_id_sesi_nomor_induk_unique');
        });

        Schema::table('absensi_pengajar', function (Blueprint $table) {
            $table->unique(['id_sesi', 'id_petugas', 'tanggal'], 'absensi_pengajar_id_sesi_id_petugas_tanggal_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensi_pengajar', function (Blueprint $table) {
            $table->dropUnique('absensi_pengajar_id_sesi_id_petugas_tanggal_unique');
        });

        Schema::table('absensi_santri', function (Blueprint $table) {
            $table->dropUnique('absensi_santri_id_sesi_nomor_induk_unique');
        });

        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->dropUnique('sesi_absensi_id_jadwal_tanggal_unique');
        });
    }
};