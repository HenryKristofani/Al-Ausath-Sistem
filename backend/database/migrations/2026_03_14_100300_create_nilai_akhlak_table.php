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
        Schema::create('nilai_akhlak', function (Blueprint $table) {
            $table->integer('id_akhlak', true)->primary();
            $table->string('nomor_induk', 20);
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->string('aspek', 80);
            $table->string('predikat', 5);
            $table->text('deskripsi')->nullable();
            $table->integer('id_petugas_input')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['nomor_induk', 'tahun_ajaran', 'semester', 'aspek'], 'uniq_akhlak_santri_tahun_semester_aspek');
            $table->index(['nomor_induk', 'tahun_ajaran', 'semester']);
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('id_petugas_input')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_akhlak');
    }
};
