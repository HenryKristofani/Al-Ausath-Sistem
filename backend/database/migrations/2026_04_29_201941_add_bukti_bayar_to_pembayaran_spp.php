<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->string('bukti_bayar_path', 500)->nullable()->after('status');
            $table->text('catatan_bayar')->nullable()->after('bukti_bayar_path');
            $table->timestamp('tanggal_konfirmasi')->nullable()->after('tanggal_verifikasi');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->dropColumn(['bukti_bayar_path', 'catatan_bayar', 'tanggal_konfirmasi']);
        });
    }
};
