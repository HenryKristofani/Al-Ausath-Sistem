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
        Schema::create('data_santri', function (Blueprint $table) {
        $table->integer('id_santri', true)->primary();
            $table->string('nomor_induk', 20)->unique();
            $table->string('nama_lengkap_santri', 200);
            $table->string('kode_kelas', 10);
            $table->string('status', 20)->default('AKTIF');
            $table->integer('tahun_masuk')->nullable();
            $table->integer('tahun_lulus')->nullable();
            $table->char('jenis_kelamin', 1)->nullable();
            $table->string('tempat_lahir', 100)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama', 50)->nullable();
            $table->decimal('berat_badan', 5, 2)->nullable();
            $table->decimal('tinggi_badan', 5, 2)->nullable();
            $table->string('gol_darah', 5)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->string('kota_kabupaten', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kelurahan', 100)->nullable();
            $table->text('alamat_tinggal')->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('alamat_email', 100)->nullable();
            $table->string('nama_ayah_kandung', 200)->nullable();
            $table->string('nama_ibu_kandung', 200)->nullable();
            $table->string('nama_wali', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('kode_kelas');
            $table->index('nama_lengkap_santri');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_santri');
    }
};