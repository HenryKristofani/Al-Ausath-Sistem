<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsolidasi semua foreign key dari migration batch awal (2026_03_10_*).
 * Dipisahkan ke sini karena tabel-tabel saling referensi dan tidak bisa
 * didefinisikan FK sebelum semua tabel selesai dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // data_akun_santri → data_santri
        Schema::table('data_akun_santri', function (Blueprint $table) {
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
        });

        // data_kelas → data_unit
        Schema::table('data_kelas', function (Blueprint $table) {
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('restrict');
        });

        // data_kelas_mapel → data_kelas, data_mata_pelajaran, data_petugas
        Schema::table('data_kelas_mapel', function (Blueprint $table) {
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('kode_mapel')->references('kode_mapel')->on('data_mata_pelajaran')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('set null');
        });

        // data_konversi_nilai → data_unit
        Schema::table('data_konversi_nilai', function (Blueprint $table) {
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('set null');
        });

        // data_mata_pelajaran → data_unit
        Schema::table('data_mata_pelajaran', function (Blueprint $table) {
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('restrict');
        });

        // data_nilai_siswa → data_santri, data_mata_pelajaran, data_kelas, data_petugas
        Schema::table('data_nilai_siswa', function (Blueprint $table) {
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('kode_mapel')->references('kode_mapel')->on('data_mata_pelajaran')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_petugas_input')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('set null');
        });

        // data_raport → data_santri, data_kelas, data_petugas
        Schema::table('data_raport', function (Blueprint $table) {
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_wali_kelas')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('set null');
        });

        // data_rekening_bank → data_unit
        Schema::table('data_rekening_bank', function (Blueprint $table) {
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('restrict');
        });

        // data_santri → data_kelas
        Schema::table('data_santri', function (Blueprint $table) {
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
        });

        // jadwal_pembelajaran → data_kelas_mapel
        Schema::table('jadwal_pembelajaran', function (Blueprint $table) {
            $table->foreign('id_kelas_mapel')->references('id_kelas_mapel')->on('data_kelas_mapel');
        });

        // kwitansi_pdf → pembayaran_spp, data_petugas
        Schema::table('kwitansi_pdf', function (Blueprint $table) {
            $table->foreign('id_pembayaran')->references('id_pembayaran')->on('pembayaran_spp');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
        });

        // log_aktivitas → data_petugas
        Schema::table('log_aktivitas', function (Blueprint $table) {
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
        });

        // log_download_raport → data_raport, data_santri, data_petugas
        Schema::table('log_download_raport', function (Blueprint $table) {
            $table->foreign('id_raport')->references('id_raport')->on('data_raport')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('set null');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('set null');
        });

        // log_perubahan_absensi → data_petugas
        Schema::table('log_perubahan_absensi', function (Blueprint $table) {
            $table->foreign('diubah_oleh')->references('id_petugas')->on('data_petugas');
        });

        // pembayaran_spp → data_santri, spp_setting, data_rekening_bank
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            $table->foreign('id_santri')->references('id_santri')->on('data_santri');
            $table->foreign('id_setting')->references('id_setting')->on('spp_setting');
            $table->foreign('id_rekening')->references('id_rekening')->on('data_rekening_bank');
        });

        // ppdb_berkas → ppdb_pendaftar
        Schema::table('ppdb_berkas', function (Blueprint $table) {
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar')->onDelete('cascade');
        });

        // ppdb_notifikasi → ppdb_pendaftar
        Schema::table('ppdb_notifikasi', function (Blueprint $table) {
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar');
        });

        // ppdb_pendaftar → akun_pendaftar
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $table->foreign('id_akun')->references('id_akun')->on('akun_pendaftar');
        });

        // ppdb_tes → ppdb_pendaftar
        Schema::table('ppdb_tes', function (Blueprint $table) {
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar')->onDelete('cascade');
        });

        // ppdb_verifikasi → ppdb_pendaftar, data_petugas
        Schema::table('ppdb_verifikasi', function (Blueprint $table) {
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
        });

        // sesi_absensi → jadwal_pembelajaran, data_petugas (x3)
        Schema::table('sesi_absensi', function (Blueprint $table) {
            $table->foreign('id_jadwal')->references('id_jadwal')->on('jadwal_pembelajaran');
            $table->foreign('id_petugas_hadir')->references('id_petugas')->on('data_petugas');
            $table->foreign('id_petugas_pengganti')->references('id_petugas')->on('data_petugas');
            $table->foreign('validated_by')->references('id_petugas')->on('data_petugas');
        });

        // spp_setting → data_unit, data_kategori_tagihan
        Schema::table('spp_setting', function (Blueprint $table) {
            $table->foreign('id_unit')->references('id_unit')->on('data_unit');
            $table->foreign('kategori_tagihan_id')->references('id_kategori')->on('data_kategori_tagihan');
        });

        // absensi_pengajar → data_petugas, sesi_absensi
        Schema::table('absensi_pengajar', function (Blueprint $table) {
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
            $table->foreign('id_sesi')->references('id_sesi')->on('sesi_absensi');
            $table->foreign('input_oleh')->references('id_petugas')->on('data_petugas');
        });

        // absensi_santri → sesi_absensi, data_petugas, data_santri
        Schema::table('absensi_santri', function (Blueprint $table) {
            $table->foreign('id_sesi')->references('id_sesi')->on('sesi_absensi');
            $table->foreign('input_oleh')->references('id_petugas')->on('data_petugas');
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_santri', fn($t) => $t->dropForeign(['id_sesi', 'input_oleh', 'nomor_induk']));
        Schema::table('absensi_pengajar', fn($t) => $t->dropForeign(['id_petugas', 'id_sesi', 'input_oleh']));
        Schema::table('spp_setting', fn($t) => $t->dropForeign(['id_unit', 'kategori_tagihan_id']));
        Schema::table('sesi_absensi', fn($t) => $t->dropForeign(['id_jadwal', 'id_petugas_hadir', 'id_petugas_pengganti', 'validated_by']));
        Schema::table('ppdb_verifikasi', fn($t) => $t->dropForeign(['id_pendaftaran', 'id_petugas']));
        Schema::table('ppdb_tes', fn($t) => $t->dropForeign(['id_pendaftaran']));
        Schema::table('ppdb_pendaftar', fn($t) => $t->dropForeign(['id_akun']));
        Schema::table('ppdb_notifikasi', fn($t) => $t->dropForeign(['id_pendaftaran']));
        Schema::table('ppdb_berkas', fn($t) => $t->dropForeign(['id_pendaftaran']));
        Schema::table('pembayaran_spp', fn($t) => $t->dropForeign(['id_santri', 'id_setting', 'id_rekening']));
        Schema::table('log_perubahan_absensi', fn($t) => $t->dropForeign(['diubah_oleh']));
        Schema::table('log_download_raport', fn($t) => $t->dropForeign(['id_raport', 'nomor_induk', 'id_petugas']));
        Schema::table('log_aktivitas', fn($t) => $t->dropForeign(['id_petugas']));
        Schema::table('kwitansi_pdf', fn($t) => $t->dropForeign(['id_pembayaran', 'id_petugas']));
        Schema::table('jadwal_pembelajaran', fn($t) => $t->dropForeign(['id_kelas_mapel']));
        Schema::table('data_santri', fn($t) => $t->dropForeign(['kode_kelas']));
        Schema::table('data_rekening_bank', fn($t) => $t->dropForeign(['kode_unit']));
        Schema::table('data_raport', fn($t) => $t->dropForeign(['nomor_induk', 'kode_kelas', 'id_wali_kelas']));
        Schema::table('data_nilai_siswa', fn($t) => $t->dropForeign(['nomor_induk', 'kode_mapel', 'kode_kelas', 'id_petugas_input']));
        Schema::table('data_mata_pelajaran', fn($t) => $t->dropForeign(['kode_unit']));
        Schema::table('data_konversi_nilai', fn($t) => $t->dropForeign(['kode_unit']));
        Schema::table('data_kelas_mapel', fn($t) => $t->dropForeign(['kode_kelas', 'kode_mapel', 'id_petugas']));
        Schema::table('data_kelas', fn($t) => $t->dropForeign(['kode_unit']));
        Schema::table('data_akun_santri', fn($t) => $t->dropForeign(['nomor_induk']));
    }
};
