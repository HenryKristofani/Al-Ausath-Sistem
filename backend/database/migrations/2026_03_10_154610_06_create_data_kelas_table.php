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
        Schema::create('data_kelas', function (Blueprint $table) {
        $table->integer('id_kelas', true)->primary();
            $table->string('kode_unit', 10);
            $table->string('kode_kelas', 10)->unique();
            $table->string('nama_kelas', 100);
            $table->string('nama_jurusan', 100)->nullable();
            $table->string('tahun_ajaran', 20);
            $table->string('status', 20)->default('AKTIF');
            $table->string('status_ppdb', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('kode_unit');
            $table->index('tahun_ajaran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_kelas');
    }
};