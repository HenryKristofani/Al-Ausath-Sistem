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
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (!Schema::hasColumn('pembayaran_spp', 'id_rekening')) {
                $table->integer('id_rekening')->nullable();
                $table->foreign('id_rekening')->references('id_rekening')->on('data_rekening_bank')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (Schema::hasColumn('pembayaran_spp', 'id_rekening')) {
                $table->dropForeign(['id_rekening']);
                $table->dropColumn('id_rekening');
            }
        });
    }
};
