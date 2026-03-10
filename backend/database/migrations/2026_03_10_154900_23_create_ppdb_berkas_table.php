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
        Schema::create('ppdb_berkas', function (Blueprint $table) {
        $table->integer('id_berkas', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->string('jenis_berkas', 80)->nullable();
            $table->text('file_path')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppdb_berkas');
    }
};