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
        Schema::create('data_kelas_mapel', function (Blueprint $table) {
        $table->integer('id_kelas_mapel', true)->primary();
            $table->string('kode_kelas', 10);
            $table->string('kode_mapel', 20);
            $table->integer('id_petugas')->nullable();
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->string('buku_acuan', 200)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['kode_kelas', 'kode_mapel', 'tahun_ajaran', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_kelas_mapel');
    }
};