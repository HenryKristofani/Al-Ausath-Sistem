<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index composite & tambahan untuk modul Administrasi.
 * Migration ini melengkapi 2026_05_24_213435_add_indexes_to_administrasi_tables.php
 * yang sudah menambahkan index tunggal (status, metode_bayar, jenjang, dll).
 *
 * Index composite dipilih berdasarkan query dominan di:
 *   - PembayaranController   : tagihan, verifikasi, proses, ringkasan
 *   - PembayaranSppController: index, tunggakanRingkasan, verifikasiPembayaran
 *   - PpdbController         : index, filter status_verifikasi+santri
 *   - DashboardController    : COUNT per status
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── pembayaran_spp ─────────────────────────────────────────────────────
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            // WHERE id_santri = ? ORDER BY id_pembayaran DESC
            if (!$this->indexExists('pembayaran_spp', 'idx_pembayaran_santri_id')) {
                $table->index(['id_santri', 'id_pembayaran'], 'idx_pembayaran_santri_id');
            }

            // WHERE id_pendaftaran = ? ORDER BY id_pembayaran DESC
            if (!$this->indexExists('pembayaran_spp', 'idx_pembayaran_pendaftaran_id')) {
                $table->index(['id_pendaftaran', 'id_pembayaran'], 'idx_pembayaran_pendaftaran_id');
            }

            // WHERE status = ? AND id_pendaftaran IS [NOT] NULL
            if (!$this->indexExists('pembayaran_spp', 'idx_pembayaran_status_pendaftaran')) {
                $table->index(['status', 'id_pendaftaran'], 'idx_pembayaran_status_pendaftaran');
            }
        });

        // ── ppdb_pendaftar ─────────────────────────────────────────────────────
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            // WHERE status_verifikasi IN (...) AND id_santri IS [NOT] NULL
            if (!$this->indexExists('ppdb_pendaftar', 'idx_pendaftar_status_santri')) {
                $table->index(['status_verifikasi', 'id_santri'], 'idx_pendaftar_status_santri');
            }

            // WHERE nama_calon LIKE '%...%'
            if (!$this->indexExists('ppdb_pendaftar', 'idx_pendaftar_nama_calon')) {
                $table->index('nama_calon', 'idx_pendaftar_nama_calon');
            }
        });

        // ── data_santri ────────────────────────────────────────────────────────
        Schema::table('data_santri', function (Blueprint $table) {
            // WHERE kode_kelas = ? AND status = ?
            if (!$this->indexExists('data_santri', 'idx_santri_kelas_status')) {
                $table->index(['kode_kelas', 'status'], 'idx_santri_kelas_status');
            }
        });

        // ── data_kelas ─────────────────────────────────────────────────────────
        Schema::table('data_kelas', function (Blueprint $table) {
            // WHERE kode_unit = ? AND tahun_ajaran = ?
            if (!$this->indexExists('data_kelas', 'idx_kelas_unit_tahun')) {
                $table->index(['kode_unit', 'tahun_ajaran'], 'idx_kelas_unit_tahun');
            }
        });

        // ── kwitansi_pdf ───────────────────────────────────────────────────────
        Schema::table('kwitansi_pdf', function (Blueprint $table) {
            // JOIN / lookup nama petugas saat generate kwitansi
            if (!$this->indexExists('kwitansi_pdf', 'idx_kwitansi_petugas')) {
                $table->index('id_petugas', 'idx_kwitansi_petugas');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return collect(\Illuminate\Support\Facades\DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $indexName]
        ))->isNotEmpty();
    }

    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->dropIndex('idx_pembayaran_santri_id');
            $table->dropIndex('idx_pembayaran_pendaftaran_id');
            $table->dropIndex('idx_pembayaran_status_pendaftaran');
        });

        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $table->dropIndex('idx_pendaftar_status_santri');
            $table->dropIndex('idx_pendaftar_nama_calon');
        });

        Schema::table('data_santri', function (Blueprint $table) {
            $table->dropIndex('idx_santri_kelas_status');
        });

        Schema::table('data_kelas', function (Blueprint $table) {
            $table->dropIndex('idx_kelas_unit_tahun');
        });

        Schema::table('kwitansi_pdf', function (Blueprint $table) {
            $table->dropIndex('idx_kwitansi_petugas');
        });
    }
};
