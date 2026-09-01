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
        Schema::create('kwitansi_pdf', function (Blueprint $table) {
        $table->integer('id_kwitansi', true)->primary();
            $table->integer('id_pembayaran')->nullable();
            $table->integer('id_petugas')->nullable();
            $table->string('jenis', 50)->nullable();
            $table->decimal('jumlah', 15, 2)->nullable();
            $table->text('file_path_pdf')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('id_pembayaran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kwitansi_pdf');
    }
};