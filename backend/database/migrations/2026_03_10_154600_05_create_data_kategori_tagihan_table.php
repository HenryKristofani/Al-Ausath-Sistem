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
        if (!Schema::hasTable('data_kategori_tagihan')) {
            Schema::create('data_kategori_tagihan', function (Blueprint $table) {
                $table->integer('id_kategori', true)->primary();
                $table->string('pilihan_unit', 10)->nullable();
                $table->string('kode_kategori', 20)->unique();
                $table->string('nama_tagihan', 200);
                $table->decimal('biaya_tagihan', 15, 2);
                $table->text('keterangan')->nullable();
                $table->string('status', 20)->default('AKTIF');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                
                $table->index('kode_kategori');
                $table->index('pilihan_unit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_kategori_tagihan');
    }
};