<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_ekskul', function (Blueprint $table) {
            $table->bigIncrements('id_ekskul');
            $table->string('kode_unit', 10)->nullable()->index();
            $table->string('nama_ekskul', 100);
            $table->text('deskripsi')->nullable();
            $table->unsignedInteger('kuota')->nullable()->comment('null = tidak terbatas');
            $table->string('status', 10)->default('AKTIF')->comment('AKTIF / NONAKTIF');
            $table->string('status_pendaftaran', 10)->default('TUTUP')->comment('BUKA / TUTUP');
            $table->timestamps();

            $table->foreign('kode_unit')
                  ->references('kode_unit')
                  ->on('data_unit')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_ekskul');
    }
};
