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
        Schema::create('bobot_nilai', function (Blueprint $table) {
            $table->integer('id_bobot', true)->primary();
            $table->string('jenjang', 20);
            $table->string('kode_unit', 10)->nullable();
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->decimal('bobot_harian', 5, 2);
            $table->decimal('bobot_uts', 5, 2);
            $table->decimal('bobot_uas', 5, 2);
            $table->decimal('bobot_kehadiran', 5, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['jenjang', 'kode_unit', 'tahun_ajaran', 'semester'], 'uniq_bobot_jenjang_unit_tahun_semester');
            $table->index(['jenjang', 'tahun_ajaran', 'semester']);
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bobot_nilai');
    }
};
