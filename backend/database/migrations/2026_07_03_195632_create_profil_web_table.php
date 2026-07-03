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
        Schema::create('profil_web', function (Blueprint $table) {
            $table->id('id_profil');
            $table->string('tipe')->unique(); // 'PAUD', 'MI', 'MTS', 'MA', 'UMUM'
            $table->string('nama');
            $table->text('visi')->nullable();
            $table->json('misi')->nullable();
            $table->text('sejarah')->nullable();
            $table->json('program_unggulan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profil_web');
    }
};
