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
        Schema::create('data_rekening_bank', function (Blueprint $table) {
        $table->integer('id_rekening', true)->primary();
            $table->string('kode_unit', 10)->nullable();
            $table->string('kode_rekening', 20)->unique();
            $table->string('nama_rekening', 200);
            $table->string('nama_pemilik', 200);
            $table->string('nomor_rekening', 50)->unique();
            $table->string('nama_bank', 100);
            $table->string('cabang_bank', 200)->nullable();
            $table->string('logo_bank', 255)->nullable();
            $table->text('peruntukan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->boolean('is_connect')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('nomor_rekening');
            $table->index('kode_unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_rekening_bank');
    }
};