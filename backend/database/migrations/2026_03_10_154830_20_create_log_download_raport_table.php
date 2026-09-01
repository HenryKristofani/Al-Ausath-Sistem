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
        Schema::create('log_download_raport', function (Blueprint $table) {
        $table->integer('id_log', true)->primary();
            $table->integer('id_raport');
            $table->string('nomor_induk', 20)->nullable();
            $table->integer('id_petugas')->nullable();
            $table->string('tipe_pengunduh', 20)->default('SANTRI');
            $table->string('aksi', 30)->default('DOWNLOAD');
            $table->string('nama_file_pdf', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status_aksi', 20)->default('SUKSES');
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_download_raport');
    }
};