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
        if (Schema::hasTable('ppdb_jawaban')) {
            Schema::drop('ppdb_jawaban');
        }

        if (Schema::hasTable('ppdb_soal')) {
            Schema::drop('ppdb_soal');
        }

        if (Schema::hasTable('ppdb_invoices')) {
            Schema::drop('ppdb_invoices');
        }

        if (Schema::hasTable('ppdb_pendaftar')) {
            Schema::table('ppdb_pendaftar', function (Blueprint $table) {
                $dropColumns = array_values(array_filter([
                    Schema::hasColumn('ppdb_pendaftar', 'status_pembayaran_ppdb') ? 'status_pembayaran_ppdb' : null,
                    Schema::hasColumn('ppdb_pendaftar', 'payment_confirmed') ? 'payment_confirmed' : null,
                    Schema::hasColumn('ppdb_pendaftar', 'payment_confirmation_channel') ? 'payment_confirmation_channel' : null,
                    Schema::hasColumn('ppdb_pendaftar', 'payment_confirmed_at') ? 'payment_confirmed_at' : null,
                ]));

                if ($dropColumns !== []) {
                    $table->dropColumn($dropColumns);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cleanup migration is intentionally not reversible.
    }
};
