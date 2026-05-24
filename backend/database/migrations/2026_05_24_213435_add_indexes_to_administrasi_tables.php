<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan index ke tabel-tabel yang berhubungan dengan modul Administrasi
 * (SPP, PPDB, Santri, Kelas) agar query pagination, filter, dan join lebih cepat.
 *
 * Index dipilih berdasarkan kolom yang sering digunakan pada:
 *   - PembayaranController (tagihan, verifikasi, proses, ringkasan)
 *   - PembayaranSppController (index, tunggakanRingkasan, verifikasiPembayaran)
 *   - PpdbController       (index, filter status_verifikasi, jenjang)
 *   - DashboardController  (COUNT query per status)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── pembayaran_spp ─────────────────────────────────────────────────────
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            // Filter status (sering digunakan di verifikasi, ringkasan, tagihan)
            $table->index('status', 'idx_pembayaran_status');

            // Filter metode bayar (proses, index)
            $table->index('metode_bayar', 'idx_pembayaran_metode');

            // Filter tanggal_bayar range (index + verifikasi filter tanggal)
            // tanggal_bayar sudah ada index dari migration 2026_03_10_154850
            // hanya tambahkan composite index untuk query paling umum:
            // WHERE id_santri = ? ORDER BY id_pembayaran DESC
            $table->index(['id_santri', 'id_pembayaran'], 'idx_pembayaran_santri_id');

            // WHERE id_pendaftaran = ? (verifikasi & tagihan detail)
            $table->index(['id_pendaftaran', 'id_pembayaran'], 'idx_pembayaran_pendaftaran_id');

            // Composite: status + id_pendaftaran (NULL check untuk filter PPDB/SPP)
            $table->index(['status', 'id_pendaftaran'], 'idx_pembayaran_status_pendaftaran');
        });

        // ── ppdb_pendaftar ─────────────────────────────────────────────────────
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            // Filter jenjang (index PPDB, export, rekapDiterima)
            $table->index('jenjang', 'idx_pendaftar_jenjang');

            // Composite: status_verifikasi + id_santri (dashboard count + integrasi check)
            $table->index(['status_verifikasi', 'id_santri'], 'idx_pendaftar_status_santri');

            // Filter nama_calon LIKE (search keyword)
            $table->index('nama_calon', 'idx_pendaftar_nama_calon');
        });

        // ── data_santri ────────────────────────────────────────────────────────
        Schema::table('data_santri', function (Blueprint $table) {
            // Filter status (dashboard, proses)
            $table->index('status', 'idx_santri_status');

            // nama_lengkap_santri sudah ada index dari migration 2026_03_10_154730
            // Composite: kode_kelas + status (proses pembayaran + filter unit)
            $table->index(['kode_kelas', 'status'], 'idx_santri_kelas_status');
        });

        // ── data_kelas ─────────────────────────────────────────────────────────
        Schema::table('data_kelas', function (Blueprint $table) {
            // tahun_ajaran sudah ada index dari migration 2026_03_10_154610
            // Composite: kode_unit + tahun_ajaran (filter proses pembayaran per unit)
            $table->index(['kode_unit', 'tahun_ajaran'], 'idx_kelas_unit_tahun');
        });

        // ── kwitansi_pdf ───────────────────────────────────────────────────────
        Schema::table('kwitansi_pdf', function (Blueprint $table) {
            // id_pembayaran sudah ada index dari migration 2026_03_10_154810
            // Tambahkan index id_petugas untuk lookup nama petugas di kwitansi
            $table->index('id_petugas', 'idx_kwitansi_petugas');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->dropIndex('idx_pembayaran_status');
            $table->dropIndex('idx_pembayaran_metode');
            $table->dropIndex('idx_pembayaran_santri_id');
            $table->dropIndex('idx_pembayaran_pendaftaran_id');
            $table->dropIndex('idx_pembayaran_status_pendaftaran');
        });

        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $table->dropIndex('idx_pendaftar_jenjang');
            $table->dropIndex('idx_pendaftar_status_santri');
            $table->dropIndex('idx_pendaftar_nama_calon');
        });

        Schema::table('data_santri', function (Blueprint $table) {
            $table->dropIndex('idx_santri_status');
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
