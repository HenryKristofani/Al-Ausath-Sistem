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
        if (! Schema::hasTable('absensi_pengajar')) {
            Schema::create('absensi_pengajar', function (Blueprint $table) {
                $table->integer('id_abs_pengajar', true)->primary();
                $table->integer('id_petugas');
                $table->integer('id_sesi')->nullable();
                $table->date('tanggal');
                $table->string('status_kehadiran', 20);
                $table->integer('menit_terlambat')->default(0);
                $table->text('keterangan')->nullable();
                $table->integer('input_oleh')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_pengajar');
    }
};