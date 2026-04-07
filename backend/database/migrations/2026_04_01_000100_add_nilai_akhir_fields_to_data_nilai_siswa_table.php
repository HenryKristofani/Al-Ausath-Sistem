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
        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->decimal('nilai_akhir_mapel', 5, 2)->nullable()->after('nilai_uas');
            $table->decimal('nilai_rapor_tampil', 5, 2)->nullable()->after('nilai_akhir_mapel');
            $table->string('flag_warna_rapor', 10)->nullable()->after('nilai_rapor_tampil');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->dropColumn([
                'nilai_akhir_mapel',
                'nilai_rapor_tampil',
                'flag_warna_rapor',
            ]);
        });
    }
};
