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
        Schema::create('data_akun_santri', function (Blueprint $table) {
        $table->integer('id_akun_santri', true)->primary();
            $table->string('nomor_induk', 20)->unique();
            $table->string('nama_akun', 100);
            $table->string('nama_lengkap', 200)->nullable();
            $table->string('nama_unit', 100)->nullable();
            $table->string('nama_kelas', 100)->nullable();
            $table->string('tahun_ajaran', 20)->nullable();
            $table->string('alamat_email', 100)->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('last_login')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('nama_akun');
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_akun_santri');
    }
};