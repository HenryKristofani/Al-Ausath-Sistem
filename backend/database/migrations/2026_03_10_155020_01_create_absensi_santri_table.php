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
        if (! Schema::hasTable('absensi_santri')) {
            Schema::create('absensi_santri', function (Blueprint $table) {
                $table->integer('id_absensi', true)->primary();
                $table->integer('id_sesi');
                $table->string('nomor_induk', 20);
                $table->string('status_kehadiran', 10);
                $table->text('keterangan')->nullable();
                $table->timestamp('timestamp_input')->useCurrent();
                $table->integer('input_oleh')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                $table->unique(['id_sesi', 'nomor_induk']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_santri');
    }
};