# Script untuk generate 30 Laravel migrations dari schema PostgreSQL
# AMAN: Hanya membuat file, TIDAK menjalankan migrations atau mengubah database

$migrationsDir = "backend\database\migrations"
$tables = @(
    "absensi_pengajar",
    "absensi_santri",
    "administrasi_bebas",
    "akun_pendaftar",
    "data_akun_santri",
    "data_kategori_tagihan",
    "data_kelas",
    "data_kelas_mapel",
    "data_konversi_nilai",
    "data_mata_pelajaran",
    "data_nilai_siswa",
    "data_petugas",
    "data_raport",
    "data_rekening_bank",
    "data_santri",
    "data_tahun_ajaran",
    "data_unit",
    "jadwal_pembelajaran",
    "kwitansi_pdf",
    "log_aktivitas",
    "log_download_raport",
    "log_perubahan_absensi",
    "pembayaran_spp",
    "ppdb_berkas",
    "ppdb_notifikasi",
    "ppdb_pendaftar",
    "ppdb_tes",
    "ppdb_verifikasi",
    "sesi_absensi",
    "spp_setting"
)

$baseTimestamp = [datetime]"2026-03-10 15:45:00"
$counter = 0

$migrationTemplates = @{
    "absensi_pengajar" = @'
        $table->integer('id_abs_pengajar', true)->primary();
            $table->integer('id_petugas');
            $table->integer('id_sesi')->nullable();
            $table->date('tanggal');
            $table->string('status_kehadiran', 20);
            $table->integer('menit_terlambat')->default(0);
            $table->text('keterangan')->nullable();
            $table->integer('input_oleh')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
            $table->foreign('id_sesi')->references('id_sesi')->on('sesi_absensi');
            $table->foreign('input_oleh')->references('id_petugas')->on('data_petugas');
'@
    
    "absensi_santri" = @'
        $table->integer('id_absensi', true)->primary();
            $table->integer('id_sesi');
            $table->string('nomor_induk', 20);
            $table->string('status_kehadiran', 10);
            $table->text('keterangan')->nullable();
            $table->timestamp('timestamp_input')->useCurrent();
            $table->integer('input_oleh')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['id_sesi', 'nomor_induk']);
            $table->foreign('id_sesi')->references('id_sesi')->on('sesi_absensi');
            $table->foreign('input_oleh')->references('id_petugas')->on('data_petugas');
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
'@
    
    "administrasi_bebas" = @'
        $table->integer('id_admin_bebas', true)->primary();
            $table->integer('id_santri')->nullable();
            $table->text('deskripsi')->nullable();
            $table->decimal('total_tagihan', 15, 2)->nullable();
            $table->decimal('sisa', 15, 2)->nullable();
            $table->string('status', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('id_santri');
            $table->foreign('id_santri')->references('id_santri')->on('data_santri');
'@
    
    "akun_pendaftar" = @'
        $table->integer('id_akun', true)->primary();
            $table->string('nama', 200);
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            
            $table->index('email');
'@
    
    "data_akun_santri" = @'
        $table->integer('id_akun_santri', true)->primary();
            $table->string('nomor_induk', 20)->unique();
            $table->string('nama_akun', 100);
            $table->string('nama_lengkap', 200)->nullable();
            $table->string('nama_unit', 100)->nullable();
            $table->string('nama_kelas', 100)->nullable();
            $table->string('tahun_ajaran', 20)->nullable();
            $table->string('alamat_email', 100)->nullable();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('last_login')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('nama_akun');
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
'@
    
    "data_kategori_tagihan" = @'
        $table->integer('id_kategori', true)->primary();
            $table->string('pilihan_unit', 10)->nullable();
            $table->string('kode_kategori', 20)->unique();
            $table->string('nama_tagihan', 200);
            $table->decimal('biaya_tagihan', 15, 2);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('kode_kategori');
            $table->index('pilihan_unit');
'@
    
    "data_kelas" = @'
        $table->integer('id_kelas', true)->primary();
            $table->string('kode_unit', 10);
            $table->string('kode_kelas', 10)->unique();
            $table->string('nama_kelas', 100);
            $table->string('nama_jurusan', 100)->nullable();
            $table->string('tahun_ajaran', 20);
            $table->string('status', 20)->default('AKTIF');
            $table->string('status_ppdb', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('kode_unit');
            $table->index('tahun_ajaran');
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('restrict');
'@
    
    "data_kelas_mapel" = @'
        $table->integer('id_kelas_mapel', true)->primary();
            $table->string('kode_kelas', 10);
            $table->string('kode_mapel', 20);
            $table->integer('id_petugas')->nullable();
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->string('buku_acuan', 200)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['kode_kelas', 'kode_mapel', 'tahun_ajaran', 'semester']);
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('kode_mapel')->references('kode_mapel')->on('data_mata_pelajaran')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('setNull');
'@
    
    "data_konversi_nilai" = @'
        $table->integer('id_konversi', true)->primary();
            $table->string('kode_unit', 10)->nullable();
            $table->decimal('nilai_min', 5, 2);
            $table->decimal('nilai_max', 5, 2);
            $table->string('nilai_huruf', 5);
            $table->string('predikat', 50)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('setNull');
'@
    
    "data_mata_pelajaran" = @'
        $table->integer('id_mapel', true)->primary();
            $table->string('kode_mapel', 20)->unique();
            $table->string('nama_mapel', 200);
            $table->string('kode_unit', 10)->nullable();
            $table->string('kelompok_mapel', 50)->nullable();
            $table->integer('urutan')->default(0);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('restrict');
'@
    
    "data_nilai_siswa" = @'
        $table->integer('id_nilai', true)->primary();
            $table->string('nomor_induk', 20);
            $table->string('kode_mapel', 20);
            $table->string('kode_kelas', 10);
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->decimal('nilai_harian', 5, 2)->nullable();
            $table->decimal('nilai_uts', 5, 2)->nullable();
            $table->decimal('nilai_uas', 5, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->integer('id_petugas_input')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['nomor_induk', 'kode_mapel', 'tahun_ajaran', 'semester']);
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('kode_mapel')->references('kode_mapel')->on('data_mata_pelajaran')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_petugas_input')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('setNull');
'@
    
    "data_petugas" = @'
        $table->integer('id_petugas', true)->primary();
            $table->string('nomor_induk', 20)->nullable();
            $table->string('nama_lengkap', 200);
            $table->string('peran_akun', 50);
            $table->string('pilihan_unit', 10)->nullable();
            $table->string('alamat_email', 100)->unique();
            $table->string('nomor_telepon', 20)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('last_login')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('alamat_email');
'@
    
    "data_raport" = @'
        $table->integer('id_raport', true)->primary();
            $table->string('nomor_induk', 20);
            $table->string('kode_kelas', 10);
            $table->string('tahun_ajaran', 20);
            $table->smallInteger('semester');
            $table->decimal('jumlah_nilai', 8, 2)->default(0);
            $table->decimal('rata_rata', 5, 2)->default(0);
            $table->integer('peringkat_kelas')->nullable();
            $table->integer('total_siswa_kelas')->nullable();
            $table->integer('hadir')->default(0);
            $table->integer('sakit')->default(0);
            $table->integer('izin')->default(0);
            $table->integer('alpha')->default(0);
            $table->string('status_raport', 20)->default('DRAFT');
            $table->text('catatan_wali')->nullable();
            $table->integer('id_wali_kelas')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->unique(['nomor_induk', 'tahun_ajaran', 'semester']);
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
            $table->foreign('id_wali_kelas')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('setNull');
'@
    
    "data_rekening_bank" = @'
        $table->integer('id_rekening', true)->primary();
            $table->string('kode_unit', 10)->nullable();
            $table->string('kode_rekening', 20)->unique();
            $table->string('nama_rekening', 200);
            $table->string('nama_pemilik', 200);
            $table->string('nomor_rekening', 50)->unique();
            $table->string('nama_bank', 100);
            $table->string('cabang_bank', 200)->nullable();
            $table->string('logo_bank', 255)->nullable();
            $table->text('peruntukan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->boolean('is_connect')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->index('nomor_rekening');
            $table->index('kode_unit');
            $table->foreign('kode_unit')->references('kode_unit')->on('data_unit')->onUpdate('cascade')->onDelete('restrict');
'@
    
    "data_santri" = @'
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
            $table->foreign('kode_kelas')->references('kode_kelas')->on('data_kelas')->onUpdate('cascade')->onDelete('restrict');
'@
    
    "data_tahun_ajaran" = @'
        $table->integer('id_tahun_ajaran', true)->primary();
            $table->string('kode_tahun', 20)->unique();
            $table->string('nama_tahun', 50);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->boolean('is_deleted')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();
'@
    
    "data_unit" = @'
        $table->integer('id_unit', true)->primary();
            $table->string('kode_unit', 10)->unique();
            $table->string('nama_unit', 100);
            $table->integer('nomor_urut')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->string('status_ppdb', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
'@
    
    "jadwal_pembelajaran" = @'
        $table->integer('id_jadwal', true)->primary();
            $table->integer('id_kelas_mapel');
            $table->string('tahun_ajaran', 20);
            $table->string('hari', 10);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('ruangan', 50)->nullable();
            $table->string('status', 20)->default('AKTIF');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            $table->foreign('id_kelas_mapel')->references('id_kelas_mapel')->on('data_kelas_mapel');
'@
    
    "kwitansi_pdf" = @'
        $table->integer('id_kwitansi', true)->primary();
            $table->integer('id_pembayaran')->nullable();
            $table->integer('id_petugas')->nullable();
            $table->string('jenis', 50)->nullable();
            $table->decimal('jumlah', 15, 2)->nullable();
            $table->text('file_path_pdf')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('id_pembayaran');
            $table->foreign('id_pembayaran')->references('id_pembayaran')->on('pembayaran_spp');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
'@
    
    "log_aktivitas" = @'
        $table->integer('id_log_aktivitas', true)->primary();
            $table->integer('id_petugas')->nullable();
            $table->string('jenis_aksi', 50);
            $table->string('modul', 50);
            $table->text('deskripsi')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
'@
    
    "log_download_raport" = @'
        $table->integer('id_log', true)->primary();
            $table->integer('id_raport');
            $table->string('nomor_induk', 20)->nullable();
            $table->integer('id_petugas')->nullable();
            $table->string('tipe_pengunduh', 20)->default('SANTRI');
            $table->string('aksi', 30)->default('DOWNLOAD');
            $table->string('nama_file_pdf', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status_aksi', 20)->default('SUKSES');
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('id_raport')->references('id_raport')->on('data_raport')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('nomor_induk')->references('nomor_induk')->on('data_santri')->onUpdate('cascade')->onDelete('setNull');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas')->onUpdate('cascade')->onDelete('setNull');
'@
    
    "log_perubahan_absensi" = @'
        $table->integer('id_log', true)->primary();
            $table->string('tabel_terkait', 50);
            $table->integer('id_record');
            $table->string('field_diubah', 50);
            $table->text('nilai_lama')->nullable();
            $table->text('nilai_baru')->nullable();
            $table->text('alasan_perubahan')->nullable();
            $table->integer('diubah_oleh')->nullable();
            $table->timestamp('diubah_pada')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            
            $table->foreign('diubah_oleh')->references('id_petugas')->on('data_petugas');
'@
    
    "pembayaran_spp" = @'
        $table->integer('id_pembayaran', true)->primary();
            $table->integer('id_santri')->nullable();
            $table->integer('id_setting')->nullable();
            $table->decimal('nominal_bayar', 15, 2)->nullable();
            $table->timestamp('tanggal_bayar')->useCurrent();
            $table->string('metode_bayar', 50)->nullable();
            $table->integer('id_rekening')->nullable();
            $table->string('status', 30)->nullable();
            
            $table->index('id_santri');
            $table->index('id_setting');
            $table->index('tanggal_bayar');
            $table->foreign('id_santri')->references('id_santri')->on('data_santri');
            $table->foreign('id_setting')->references('id_setting')->on('spp_setting');
            $table->foreign('id_rekening')->references('id_rekening')->on('data_rekening_bank');
'@
    
    "ppdb_berkas" = @'
        $table->integer('id_berkas', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->string('jenis_berkas', 80)->nullable();
            $table->text('file_path')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar')->onDelete('cascade');
'@
    
    "ppdb_notifikasi" = @'
        $table->integer('id_notif', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->string('type', 20)->nullable();
            $table->text('konten')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status_kirim', 20)->nullable();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar');
'@
    
    "ppdb_pendaftar" = @'
        $table->integer('id_pendaftaran', true)->primary();
            $table->integer('id_akun')->nullable();
            $table->string('no_pendaftaran', 50)->unique();
            $table->string('no_pendaftaran_final', 50)->nullable();
            $table->string('nama_calon', 200);
            $table->string('jenjang', 20)->nullable();
            $table->string('nomor_umi', 50)->nullable();
            $table->string('asal_kota', 100)->nullable();
            $table->boolean('is_luar_kota')->default(false);
            $table->string('status_verifikasi', 30)->default('pending');
            $table->date('tanggal_daftar')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('id_akun');
            $table->index('no_pendaftaran');
            $table->index('status_verifikasi');
            $table->foreign('id_akun')->references('id_akun')->on('akun_pendaftar');
'@
    
    "ppdb_tes" = @'
        $table->integer('id_tes', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->decimal('nilai', 15, 2)->nullable();
            $table->string('status_tes', 30)->nullable();
            $table->text('catatan')->nullable();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar')->onDelete('cascade');
'@
    
    "ppdb_verifikasi" = @'
        $table->integer('id_verif', true)->primary();
            $table->integer('id_pendaftaran')->nullable();
            $table->integer('id_petugas')->nullable();
            $table->timestamp('tanggal_verif')->useCurrent();
            $table->string('hasil', 20)->nullable();
            $table->text('catatan')->nullable();
            
            $table->index('id_pendaftaran');
            $table->foreign('id_pendaftaran')->references('id_pendaftaran')->on('ppdb_pendaftar');
            $table->foreign('id_petugas')->references('id_petugas')->on('data_petugas');
'@
    
    "sesi_absensi" = @'
        $table->integer('id_sesi', true)->primary();
            $table->integer('id_jadwal');
            $table->integer('id_petugas_hadir');
            $table->integer('id_petugas_pengganti')->nullable();
            $table->date('tanggal');
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->string('status_sesi', 20)->default('BERLANGSUNG');
            $table->text('keterangan')->nullable();
            $table->boolean('is_validated')->default(false);
            $table->integer('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('id_jadwal')->references('id_jadwal')->on('jadwal_pembelajaran');
            $table->foreign('id_petugas_hadir')->references('id_petugas')->on('data_petugas');
            $table->foreign('id_petugas_pengganti')->references('id_petugas')->on('data_petugas');
            $table->foreign('validated_by')->references('id_petugas')->on('data_petugas');
'@
    
    "spp_setting" = @'
        $table->integer('id_setting', true)->primary();
            $table->integer('id_unit')->nullable();
            $table->string('jenjang', 20)->nullable();
            $table->integer('kategori_tagihan_id')->nullable();
            $table->decimal('jumlah', 15, 2)->nullable();
            $table->string('periode', 20)->nullable();
            $table->text('keterangan')->nullable();
            
            $table->index('id_unit');
            $table->index('kategori_tagihan_id');
            $table->foreign('id_unit')->references('id_unit')->on('data_unit');
            $table->foreign('kategori_tagihan_id')->references('id_kategori')->on('data_kategori_tagihan');
'@
}

Write-Host "`n🚀 Generating 30 Laravel migrations..." -ForegroundColor Cyan
Write-Host "=" * 60

foreach ($tableName in $tables) {
    $counter++
    $migrationTime = $baseTimestamp.AddSeconds($counter * 10)
    $timestamp = $migrationTime.ToString("yyyy_MM_dd_HHmmss")
    $paddedNum = "{0:D2}" -f ($counter - 1)
    $fileName = "${timestamp}_${paddedNum}_create_${tableName}_table.php"
    $filePath = Join-Path $migrationsDir $fileName
    
    $columns = $migrationTemplates[$tableName]
    
    $content = @"
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
        Schema::create('$tableName', function (Blueprint `$table) {
${columns}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('$tableName');
    }
};
"@
    
    $content | Out-File -FilePath $filePath -Encoding UTF8 -NoNewline
    Write-Host "✅ $counter. $tableName" -ForegroundColor Green
}

Write-Host "=" * 60
Write-Host "`nCreated $counter migrations successfully!" -ForegroundColor Yellow
Write-Host "Location: backend/database/migrations/" -ForegroundColor Cyan
Write-Host "`n[IMPORTANT NOTES]" -ForegroundColor Red
Write-Host "   * Migrations are CREATED but NOT YET EXECUTED" -ForegroundColor Gray
Write-Host "   * Database is NOT affected" -ForegroundColor Gray
Write-Host "   * To run migrations: php artisan migrate" -ForegroundColor Green
Write-Host "   * To rollback: php artisan migrate:rollback" -ForegroundColor Green
