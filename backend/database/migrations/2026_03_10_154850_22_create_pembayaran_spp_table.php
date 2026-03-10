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
        Schema::create('pembayaran_spp', function (Blueprint $table) {
        $table->integer('id_pembayaran', true)->primary();
            $table->integer('id_santri')->nullable();
            $table->integer('id_setting')->nullable();
            $table->decimal('nominal_bayar', 15, 2)->nullable();
            $table->timestamp('tanggal_bayar')->useCurrent();
            $table->string('metode_bayar', 50)->nullable();
            $table->integer('id_rekening')->nullable();
            $table->string('status', 30)->nullable();
            
            $table->index('id_santri');
            $table->index('id_setting');
            $table->index('tanggal_bayar');
            $table->foreign('id_santri')->references('id_santri')->on('data_santri');
            $table->foreign('id_setting')->references('id_setting')->on('spp_setting');
            $table->foreign('id_rekening')->references('id_rekening')->on('data_rekening_bank');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_spp');
    }
};