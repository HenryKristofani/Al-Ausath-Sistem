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
        if (! Schema::hasTable('akun_pendaftar')) {
            Schema::create('akun_pendaftar', function (Blueprint $table) {
                $table->integer('id_akun', true)->primary();
                $table->string('nama', 200);
                $table->string('email', 150)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('password_hash', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->nullable();

                $table->index('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akun_pendaftar');
    }
};