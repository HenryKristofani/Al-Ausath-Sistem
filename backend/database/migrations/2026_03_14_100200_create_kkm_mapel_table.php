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
        Schema::create('kkm_mapel', function (Blueprint $table) {
            $table->integer('id_kkm', true)->primary();
            $table->string('kode_mapel', 20);
            $table->string('jenjang', 20);
            $table->string('kode_unit', 10)->nullable();
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->decimal('nilai_kkm', 5, 2);
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['kode_mapel', 'jenjang', 'tahun_ajaran', 'semester'], 'uniq_kkm_mapel_jenjang_tahun_semester');
            $table->index(['jenjang', 'tahun_ajaran', 'semester']);
            $table->foreign('kode_mapel')->references('kode_mapel')->on('data_mata_pelajaran')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kkm_mapel');
    }
};
