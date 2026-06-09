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
        // For PostgreSQL, use USING clause to convert existing string to a JSON array containing that string
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE data_petugas ALTER COLUMN peran_akun TYPE JSONB USING json_build_array(peran_akun)::jsonb");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to VARCHAR, extracting the first element from the JSON array
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE data_petugas ALTER COLUMN peran_akun TYPE VARCHAR(100) USING peran_akun->>0");
    }
};
