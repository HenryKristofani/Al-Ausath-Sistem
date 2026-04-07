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
        Schema::create('administrasi_bebas_pembayaran', function (Blueprint $table) {
            $table->integer('id_bayar_bebas', true)->primary();
            $table->integer('id_admin_bebas');
            $table->integer('id_petugas')->nullable();
            $table->decimal('nominal_bayar', 15, 2);
            $table->timestamp('tanggal_bayar')->useCurrent();
            $table->string('metode_bayar', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('id_admin_bebas');
            $table->index('id_petugas');
            $table->index('tanggal_bayar');
            $table->foreign('id_admin_bebas')
                ->references('id_admin_bebas')
                ->on('administrasi_bebas')
                ->onDelete('cascade');
            $table->foreign('id_petugas')
                ->references('id_petugas')
                ->on('data_petugas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrasi_bebas_pembayaran');
    }
};
