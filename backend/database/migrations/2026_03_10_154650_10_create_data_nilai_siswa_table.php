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