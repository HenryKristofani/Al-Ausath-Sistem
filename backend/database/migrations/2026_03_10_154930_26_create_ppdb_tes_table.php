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
        Schema::create('ppdb_tes', function (Blueprint $table) {
        $table->integer('id_tes', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->decimal('nilai', 15, 2)->nullable();
            $table->string('status_tes', 30)->nullable();
            $table->text('catatan')->nullable();
            
            $table->index('id_pendaftaran');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_tes');
    }
};