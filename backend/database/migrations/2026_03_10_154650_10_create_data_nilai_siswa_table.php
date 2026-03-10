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
        Schema::create('data_nilai_siswa', function (Blueprint $table) {
        $table->integer('id_nilai', true)->primary();
            $table->string('nomor_induk', 20);
            $table->string('kode_mapel', 20);
            $table->string('kode_kelas', 10);
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->decimal('nilai_harian', 5, 2)->nullable();
            $table->decimal('nilai_uts', 5, 2)->nullable();
            $table->decimal('nilai_uas', 5, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->integer('id_petugas_input')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['nomor_induk', 'kode_mapel', 'tahun_ajaran', 'semester']);
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('kode_mapel')->references('kode_mapel')->on('data_mata_pelajaran')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_petugas_input')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('setNull');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_nilai_siswa');
    }
};