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
        if (!Schema::hasTable('ppdb_tes_konfigurasi')) {
            Schema::create('ppdb_tes_konfigurasi', function (Blueprint $table) {
                $table->id('id_konfigurasi');
                $table->string('jenjang', 20)->unique();
                $table->boolean('fitur_soal_aktif')->default(false);
                $table->text('soal_tes')->nullable();
                $table->json('form_schema')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_tes_konfigurasi');
    }
};
