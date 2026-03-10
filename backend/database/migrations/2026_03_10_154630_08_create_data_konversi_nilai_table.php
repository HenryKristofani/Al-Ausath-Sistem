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
        Schema::create('data_konversi_nilai', function (Blueprint $table) {
        $table->integer('id_konversi', true)->primary();
            $table->string('kode_unit', 10)->nullable();
            $table->decimal('nilai_min', 5, 2);
            $table->decimal('nilai_max', 5, 2);
            $table->string('nilai_huruf', 5);
            $table->string('predikat', 50)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('setNull');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_konversi_nilai');
    }
};