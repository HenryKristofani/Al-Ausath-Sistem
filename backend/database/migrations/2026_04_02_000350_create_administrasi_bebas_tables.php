<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration ini dibuat untuk melengkapi tabel administrasi_bebas dan
 * administrasi_bebas_pembayaran yang sebelumnya tidak ada migration create-nya,
 * padahal sudah dirujuk oleh migration-migration lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabel tagihan bebas (non-SPP): uang gedung, denda, dll
        if (!Schema::hasTable('administrasi_bebas')) {
            Schema::create('administrasi_bebas', function (Blueprint $table) {
                $table->integer('id_admin_bebas', true)->primary();
                $table->integer('id_santri')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('kategori', 50)->nullable();
                $table->string('tahun_ajaran', 20)->nullable();
                $table->decimal('total_tagihan', 15, 2)->default(0);
                $table->decimal('sisa', 15, 2)->default(0);
                $table->string('status', 30)->default('BELUM_LUNAS');
                $table->timestamp('created_at')->useCurrent();

                $table->index('id_santri');
                $table->index('status');
                $table->foreign('id_santri')->references('id_santri')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // Tabel pembayaran untuk tagihan bebas
        if (!Schema::hasTable('administrasi_bebas_pembayaran')) {
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
                $table->foreign('id_admin_bebas')->references('id_admin_bebas')->on('administrasi_bebas')->onDelete('cascade');
                $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('administrasi_bebas_pembayaran');
        Schema::dropIfExists('administrasi_bebas');
    }
};
