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
        Schema::create('data_unit', function (Blueprint $table) {
        $table->integer('id_unit', true)->primary();
            $table->string('kode_unit', 10)->unique();
            $table->string('nama_unit', 100);
            $table->integer('nomor_urut')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->string('status_ppdb', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_unit');
    }
};