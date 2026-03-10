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
        Schema::create('data_tahun_ajaran', function (Blueprint $table) {
        $table->integer('id_tahun_ajaran', true)->primary();
            $table->string('kode_tahun', 20)->unique();
            $table->string('nama_tahun', 50);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_tahun_ajaran');
    }
};