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
        Schema::create('spp_setting', function (Blueprint $table) {
        $table->integer('id_setting', true)->primary();
            $table->integer('id_unit')->nullable();
            $table->string('jenjang', 20)->nullable();
            $table->integer('kategori_tagihan_id')->nullable();
            $table->decimal('jumlah', 15, 2)->nullable();
            $table->string('periode', 20)->nullable();
            $table->text('keterangan')->nullable();
            
            $table->index('id_unit');
            $table->index('kategori_tagihan_id');
            $table->foreign('id_unit')->references('id_unit')->on('data_unit');
            $table->foreign('kategori_tagihan_id')->references('id_kategori')->on('data_kategori_tagihan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spp_setting');
    }
};