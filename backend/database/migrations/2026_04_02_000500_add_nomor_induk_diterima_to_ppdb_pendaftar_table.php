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
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $table->string('nomor_induk_generated', 20)->nullable()->after('no_pendaftaran_final');
            $table->string('kode_kelas_diterima', 10)->nullable()->after('asal_kota');
            $table->integer('id_santri')->nullable()->after('id_akun');
            $table->date('tanggal_diterima')->nullable()->after('tanggal_daftar');

            $table->index('id_santri');
            $table->unique('nomor_induk_generated');
            $table->foreign('id_santri')
                ->references('id_santri')
                ->on('data_santri')
                ->nullOnDelete();
            $table->foreign('kode_kelas_diterima')
                ->references('kode_kelas')
                ->on('data_kelas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $table->dropForeign(['id_santri']);
            $table->dropForeign(['kode_kelas_diterima']);
            $table->dropIndex(['id_santri']);
            $table->dropUnique(['nomor_induk_generated']);
            $table->dropColumn([
                'id_santri',
                'nomor_induk_generated',
                'kode_kelas_diterima',
                'tanggal_diterima',
            ]);
        });
    }
};
