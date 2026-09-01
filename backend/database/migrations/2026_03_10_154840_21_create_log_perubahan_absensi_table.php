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
        Schema::create('log_perubahan_absensi', function (Blueprint $table) {
        $table->integer('id_log', true)->primary();
            $table->string('tabel_terkait', 50);
            $table->integer('id_record');
            $table->string('field_diubah', 50);
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();
            $table->text('alasan_perubahan')->nullable();
            $table->integer('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->useCurrent();
            $table->string('ip_address', 45)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_perubahan_absensi');
    }
};