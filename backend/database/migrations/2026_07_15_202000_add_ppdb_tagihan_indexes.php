<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── ppdb_tagihan ───────────────────────────────────────────────────────
        // Menambahkan index pada foreign key id_pendaftaran dan id_santri di ppdb_tagihan
        // agar query filter detail tagihan per santri/calon santri cepat.
        if (Schema::hasTable('ppdb_tagihan')) {
            Schema::table('ppdb_tagihan', function (Blueprint $table) {
                if (!$this->indexExists('ppdb_tagihan', 'idx_ppdb_tagihan_pendaftaran')) {
                    $table->index('id_pendaftaran', 'idx_ppdb_tagihan_pendaftaran');
                }
                if (!$this->indexExists('ppdb_tagihan', 'idx_ppdb_tagihan_santri')) {
                    $table->index('id_santri', 'idx_ppdb_tagihan_santri');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ppdb_tagihan')) {
            Schema::table('ppdb_tagihan', function (Blueprint $table) {
                if ($this->indexExists('ppdb_tagihan', 'idx_ppdb_tagihan_pendaftaran')) {
                    $table->dropIndex('idx_ppdb_tagihan_pendaftaran');
                }
                if ($this->indexExists('ppdb_tagihan', 'idx_ppdb_tagihan_santri')) {
                    $table->dropIndex('idx_ppdb_tagihan_santri');
                }
            });
        }
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
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
