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
        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            if (!Schema::hasColumn('ppdb_pendaftar', 'program_pendaftaran')) {
                $table->string('program_pendaftaran', 100)->nullable()->after('nama_calon');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'jenis_kelamin')) {
                $table->char('jenis_kelamin', 1)->nullable()->after('jenjang');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'tempat_lahir')) {
                $table->string('tempat_lahir', 100)->nullable()->after('jenis_kelamin');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'tanggal_lahir')) {
                $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'nik_calon_santri')) {
                $table->string('nik_calon_santri', 30)->nullable()->after('tanggal_lahir');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'alamat_lengkap')) {
                $table->text('alamat_lengkap')->nullable()->after('nik_calon_santri');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'riwayat_penyakit')) {
                $table->text('riwayat_penyakit')->nullable()->after('alamat_lengkap');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'nama_ayah')) {
                $table->string('nama_ayah', 200)->nullable()->after('riwayat_penyakit');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'penghasilan_ayah')) {
                $table->string('penghasilan_ayah', 100)->nullable()->after('nama_ayah');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'no_hp_calon')) {
                $table->string('no_hp_calon', 30)->nullable()->after('penghasilan_ayah');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'nama_ibu')) {
                $table->string('nama_ibu', 200)->nullable()->after('no_hp_calon');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'no_hp_ibu')) {
                $table->string('no_hp_ibu', 30)->nullable()->after('nama_ibu');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'soal_jawab')) {
                $table->text('soal_jawab')->nullable()->after('no_hp_ibu');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'file_akta_path')) {
                $table->text('file_akta_path')->nullable()->after('soal_jawab');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'file_kk_path')) {
                $table->text('file_kk_path')->nullable()->after('file_akta_path');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'file_surat_rekomendasi_path')) {
                $table->text('file_surat_rekomendasi_path')->nullable()->after('file_kk_path');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'surat_pernyataan_setuju')) {
                $table->boolean('surat_pernyataan_setuju')->default(false)->after('file_surat_rekomendasi_path');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'surat_pernyataan_file_path')) {
                $table->text('surat_pernyataan_file_path')->nullable()->after('surat_pernyataan_setuju');
            }
            if (!Schema::hasColumn('ppdb_pendaftar', 'waktu_pendaftaran')) {
                $table->timestamp('waktu_pendaftaran')->nullable()->after('tanggal_daftar');
            }
        });

        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (!Schema::hasColumn('pembayaran_spp', 'id_pendaftaran')) {
                $table->integer('id_pendaftaran')->nullable()->after('id_pembayaran');
                $table->index('id_pendaftaran', 'pembayaran_spp_id_pendaftaran_index');
                $table->foreign('id_pendaftaran', 'pembayaran_spp_id_pendaftaran_foreign')
                    ->references('id_pendaftaran')
                    ->on('ppdb_pendaftar')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('pembayaran_spp', 'tanggal_verifikasi')) {
                $table->timestamp('tanggal_verifikasi')->nullable()->after('status');
            }

            if (!Schema::hasColumn('pembayaran_spp', 'id_petugas_verifikator')) {
                $table->integer('id_petugas_verifikator')->nullable()->after('tanggal_verifikasi');
                $table->foreign('id_petugas_verifikator', 'pembayaran_spp_id_petugas_verifikator_foreign')
                    ->references('id_petugas')
                    ->on('data_petugas')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if (Schema::hasColumn('pembayaran_spp', 'id_petugas_verifikator')) {
                $table->dropForeign('pembayaran_spp_id_petugas_verifikator_foreign');
            }

            if (Schema::hasColumn('pembayaran_spp', 'id_pendaftaran')) {
                $table->dropForeign('pembayaran_spp_id_pendaftaran_foreign');
                $table->dropIndex('pembayaran_spp_id_pendaftaran_index');
            }

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('pembayaran_spp', 'id_pendaftaran') ? 'id_pendaftaran' : null,
                Schema::hasColumn('pembayaran_spp', 'tanggal_verifikasi') ? 'tanggal_verifikasi' : null,
                Schema::hasColumn('pembayaran_spp', 'id_petugas_verifikator') ? 'id_petugas_verifikator' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('ppdb_pendaftar', function (Blueprint $table) {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('ppdb_pendaftar', 'program_pendaftaran') ? 'program_pendaftaran' : null,
                Schema::hasColumn('ppdb_pendaftar', 'jenis_kelamin') ? 'jenis_kelamin' : null,
                Schema::hasColumn('ppdb_pendaftar', 'tempat_lahir') ? 'tempat_lahir' : null,
                Schema::hasColumn('ppdb_pendaftar', 'tanggal_lahir') ? 'tanggal_lahir' : null,
                Schema::hasColumn('ppdb_pendaftar', 'nik_calon_santri') ? 'nik_calon_santri' : null,
                Schema::hasColumn('ppdb_pendaftar', 'alamat_lengkap') ? 'alamat_lengkap' : null,
                Schema::hasColumn('ppdb_pendaftar', 'riwayat_penyakit') ? 'riwayat_penyakit' : null,
                Schema::hasColumn('ppdb_pendaftar', 'nama_ayah') ? 'nama_ayah' : null,
                Schema::hasColumn('ppdb_pendaftar', 'penghasilan_ayah') ? 'penghasilan_ayah' : null,
                Schema::hasColumn('ppdb_pendaftar', 'no_hp_calon') ? 'no_hp_calon' : null,
                Schema::hasColumn('ppdb_pendaftar', 'nama_ibu') ? 'nama_ibu' : null,
                Schema::hasColumn('ppdb_pendaftar', 'no_hp_ibu') ? 'no_hp_ibu' : null,
                Schema::hasColumn('ppdb_pendaftar', 'soal_jawab') ? 'soal_jawab' : null,
                Schema::hasColumn('ppdb_pendaftar', 'file_akta_path') ? 'file_akta_path' : null,
                Schema::hasColumn('ppdb_pendaftar', 'file_kk_path') ? 'file_kk_path' : null,
                Schema::hasColumn('ppdb_pendaftar', 'file_surat_rekomendasi_path') ? 'file_surat_rekomendasi_path' : null,
                Schema::hasColumn('ppdb_pendaftar', 'surat_pernyataan_setuju') ? 'surat_pernyataan_setuju' : null,
                Schema::hasColumn('ppdb_pendaftar', 'surat_pernyataan_file_path') ? 'surat_pernyataan_file_path' : null,
                Schema::hasColumn('ppdb_pendaftar', 'waktu_pendaftaran') ? 'waktu_pendaftaran' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
