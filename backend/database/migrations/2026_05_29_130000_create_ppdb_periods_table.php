<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppdb_periods', function (Blueprint $table) {
            $table->id();
            $table->string('nama_gelombang', 100);
            $table->string('tahun_ajaran', 20);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->integer('kuota')->nullable();
            $table->decimal('biaya_pendaftaran', 15, 2)->default(100000);
            $table->enum('status', ['draft', 'aktif', 'ditutup', 'selesai'])->default('draft');
            $table->text('deskripsi')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['tahun_ajaran']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });

        // Tambah kolom ppdb_period_id ke tabel ppdb_pendaftar
        if (Schema::hasTable('ppdb_pendaftar') && !Schema::hasColumn('ppdb_pendaftar', 'ppdb_period_id')) {
            Schema::table('ppdb_pendaftar', function (Blueprint $table) {
                $table->unsignedBigInteger('ppdb_period_id')->nullable()->after('id_akun');
                $table->index('ppdb_period_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ppdb_pendaftar') && Schema::hasColumn('ppdb_pendaftar', 'ppdb_period_id')) {
            Schema::table('ppdb_pendaftar', function (Blueprint $table) {
                $table->dropColumn('ppdb_period_id');
            });
        }

        Schema::dropIfExists('ppdb_periods');
    }
};
