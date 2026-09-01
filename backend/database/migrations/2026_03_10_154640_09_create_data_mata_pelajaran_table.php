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
        Schema::create('data_mata_pelajaran', function (Blueprint $table) {
        $table->integer('id_mapel', true)->primary();
            $table->string('kode_mapel', 20)->unique();
            $table->string('nama_mapel', 200);
            $table->string('kode_unit', 10)->nullable();
            $table->string('kelompok_mapel', 50)->nullable();
            $table->integer('urutan')->default(0);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_mata_pelajaran');
    }
};