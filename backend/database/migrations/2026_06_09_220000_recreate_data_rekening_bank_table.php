<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Re-create data_rekening_bank table for admin to manage bank accounts
     * displayed on payment pages (PPDB, SPP, Infaq).
     */
    public function up(): void
    {
        if (Schema::hasTable('data_rekening_bank')) {
            return;
        }

        Schema::create('data_rekening_bank', function (Blueprint $table) {
            $table->integer('id_rekening', true)->primary();
            $table->string('nama_rekening', 200);
            $table->string('nama_pemilik', 200);
            $table->string('nomor_rekening', 50)->unique();
            $table->string('nama_bank', 100);
            $table->string('cabang_bank', 200)->nullable();
            $table->text('peruntukan')->nullable()->comment('Keterangan peruntukan: PPDB, SPP, Infaq, dsb');
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('nomor_rekening');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_rekening_bank');
    }
};
