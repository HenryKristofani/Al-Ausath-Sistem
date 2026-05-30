<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pendaftaran_ekskul', function (Blueprint $table) {
            $table->bigIncrements('id_pendaftaran');
            $table->unsignedBigInteger('id_santri');
            $table->unsignedBigInteger('id_ekskul');
            $table->timestamps();

            // 1 santri hanya boleh 1 ekskul
            $table->unique('id_santri');

            $table->foreign('id_santri')
                  ->references('id_santri')
                  ->on('data_santri')
                  ->cascadeOnDelete();

            $table->foreign('id_ekskul')
                  ->references('id_ekskul')
                  ->on('data_ekskul')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pendaftaran_ekskul');
    }
};
