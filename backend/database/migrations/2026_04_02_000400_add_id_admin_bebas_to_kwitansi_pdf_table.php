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
        Schema::table('kwitansi_pdf', function (Blueprint $table) {
            $table->integer('id_admin_bebas')->nullable()->after('id_pembayaran');
            $table->index('id_admin_bebas');
            $table->foreign('id_admin_bebas')
                ->references('id_admin_bebas')
                ->on('administrasi_bebas')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kwitansi_pdf', function (Blueprint $table) {
            $table->dropForeign(['id_admin_bebas']);
            $table->dropIndex(['id_admin_bebas']);
            $table->dropColumn('id_admin_bebas');
        });
    }
};
