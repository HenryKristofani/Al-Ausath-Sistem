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
        Schema::create('ppdb_notifikasi', function (Blueprint $table) {
        $table->integer('id_notif', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->string('type', 20)->nullable();
            $table->text('konten')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status_kirim', 20)->nullable();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_notifikasi');
    }
};