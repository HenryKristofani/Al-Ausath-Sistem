<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Covering indexes untuk query tagihan yang sangat berat.
 * 
 * Query utama di PembayaranController::tagihan() melakukan:
 *   GROUP BY id_santri pada pembayaran_spp → butuh covering index (id_santri, status, nominal_bayar)
 *   GROUP BY id_santri pada administrasi_bebas → butuh index id_santri
 *   JOIN data_kelas ON kode_kelas → sudah ada
 *   JOIN data_unit ON kode_unit → tambahkan index
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── pembayaran_spp ─────────────────────────────────────────────────────
        // Covering index untuk GROUP BY id_santri dengan SUM(nominal_bayar) per status
        // Query: SELECT id_santri, SUM(CASE WHEN status IN (...) THEN nominal_bayar ELSE 0 END)
        //        FROM pembayaran_spp GROUP BY id_santri
        // Dengan covering index ini DB tidak perlu baca row data, cukup baca index saja
        if (!$this->indexExists('pembayaran_spp', 'idx_ps_santri_status_nominal')) {
            Schema::table('pembayaran_spp', function (Blueprint $table) {
                $table->index(
                    ['id_santri', 'status', 'nominal_bayar'],
                    'idx_ps_santri_status_nominal'
                );
            });
        }

        // Covering index untuk GROUP BY id_pendaftaran (PPDB aggregate)
        if (!$this->indexExists('pembayaran_spp', 'idx_ps_pendaftaran_status_nominal')) {
            Schema::table('pembayaran_spp', function (Blueprint $table) {
                $table->index(
                    ['id_pendaftaran', 'status', 'nominal_bayar'],
                    'idx_ps_pendaftaran_status_nominal'
                );
            });
        }

        // ── administrasi_bebas ────────────────────────────────────────────────
        // Index untuk GROUP BY id_santri dengan SUM(total_tagihan, sisa)
        if (!$this->indexExists('administrasi_bebas', 'idx_ab_santri_tagihan_sisa')) {
            Schema::table('administrasi_bebas', function (Blueprint $table) {
                $table->index(
                    ['id_santri', 'total_tagihan', 'sisa'],
                    'idx_ab_santri_tagihan_sisa'
                );
            });
        }

        // ── data_unit ─────────────────────────────────────────────────────────
        // Index untuk JOIN data_unit ON kode_unit (dari data_kelas ke data_unit)
        // kode_unit sudah UNIQUE tapi pastikan index ada untuk lookup cepat
        // (unique constraint sudah jadi index, jadi skip)

        // ── data_kelas ────────────────────────────────────────────────────────
        // Covering index untuk JOIN dari data_santri (kode_kelas) ke data_kelas
        // dan ambil kolom nama_kelas, tahun_ajaran, kode_unit
        if (!$this->indexExists('data_kelas', 'idx_kelas_covering')) {
            Schema::table('data_kelas', function (Blueprint $table) {
                $table->index(
                    ['kode_kelas', 'nama_kelas', 'tahun_ajaran', 'kode_unit'],
                    'idx_kelas_covering'
                );  
            });
        }
    }

    public function down(): void
    {
        Schema::table('pembayaran_spp', function (Blueprint $table) {
            if ($this->indexExists('pembayaran_spp', 'idx_ps_santri_status_nominal')) {
                $table->dropIndex('idx_ps_santri_status_nominal');
            }
            if ($this->indexExists('pembayaran_spp', 'idx_ps_pendaftaran_status_nominal')) {
                $table->dropIndex('idx_ps_pendaftaran_status_nominal');
            }
        });

        Schema::table('administrasi_bebas', function (Blueprint $table) {
            if ($this->indexExists('administrasi_bebas', 'idx_ab_santri_tagihan_sisa')) {
                $table->dropIndex('idx_ab_santri_tagihan_sisa');
            }
        });

        Schema::table('data_kelas', function (Blueprint $table) {
            if ($this->indexExists('data_kelas', 'idx_kelas_covering')) {
                $table->dropIndex('idx_kelas_covering');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            return count(DB::select(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, strtolower($indexName)]
            )) > 0;
        }
        if ($driver === 'sqlite') {
            return count(DB::select("PRAGMA index_info(?)", [$indexName])) > 0;
        }
        // MySQL fallback
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
