<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Akademik\BobotNilaiController;
use App\Http\Controllers\Api\Akademik\KkmMapelController;
use App\Http\Controllers\Api\Akademik\KonversiNilaiController;
use App\Http\Controllers\Api\Akademik\NilaiMapelController;
use App\Http\Controllers\Api\Akademik\NilaiAkhlakController;
use App\Http\Controllers\Api\Akademik\NilaiStatistikController;
use App\Http\Controllers\Api\Akademik\RaportCatatanWaliController;
use App\Http\Controllers\Api\Akademik\RaportGenerateController;
use App\Http\Controllers\Api\Akademik\RaportPdfController;
use App\Http\Controllers\Api\Akademik\RaportKeseharianController;
use App\Http\Controllers\Api\Akademik\RangkingKelasController;
use App\Http\Controllers\Api\DataMaster\DataAkunSantriController;
use App\Http\Controllers\Api\DataMaster\DataKelasController;
use App\Http\Controllers\Api\DataMaster\DataKelasMapelController;
use App\Http\Controllers\Api\DataMaster\DataJadwalPembelajaranController;
use App\Http\Controllers\Api\DataMaster\DataMataPelajaranController;
use App\Http\Controllers\Api\DataMaster\DataSantriController;
use App\Http\Controllers\Api\DataMaster\DataPetugasController;
use App\Http\Controllers\Api\DataMaster\DataTahunAjaranController;
use App\Http\Controllers\Api\DataMaster\DataUnitController;
use App\Http\Controllers\Api\Administrasi\PembayaranSppController;
use App\Http\Controllers\Api\Administrasi\DashboardController;
use App\Http\Controllers\Api\Administrasi\PpdbController;
use App\Http\Controllers\Api\Administrasi\PpdbTesKonfigurasiController;
use App\Http\Controllers\Api\Administrasi\SppGolonganController;
use App\Http\Controllers\Api\Akademik\SesiAbsensiController;
use App\Http\Controllers\Api\Akademik\AdminSesiAbsensiController;
use App\Http\Controllers\Api\Akademik\RekapAbsensiController;
use App\Http\Controllers\Api\Akademik\DashboardPresensiController;
use App\Http\Controllers\Api\Administrasi\SppSettingController;
use App\Http\Controllers\Api\Administrasi\PengumumanController;
use App\Http\Controllers\Api\Administrasi\PembayaranController;
use App\Http\Controllers\Api\Administrasi\AdministrasiBebasController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);

Route::prefix('ppdb')->group(function () {
    Route::post('/login', [AuthController::class, 'loginPpdb']);
    Route::post('/register', [AuthController::class, 'registerPpdb']);
    Route::post('/pendaftaran/create-identitas', [AuthController::class, 'createIdentitasPendaftaranPpdb']);
    Route::get('/nomor/preview', [AuthController::class, 'previewNoPendaftaran']);
    Route::get('/pengumuman/rekap', [AuthController::class, 'rekapPengumumanPpdb']);
});

// Public route untuk landing page — tanpa auth
Route::get('/pengumuman', [PengumumanController::class, 'indexPublic']);
Route::get('/pengumuman/{id}', [PengumumanController::class, 'showPublic']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::prefix('ppdb')->group(function () {
        Route::get('/dashboard', [AuthController::class, 'dashboardPpdb']);
        Route::get('/tes', [AuthController::class, 'tesStatusPpdb']);
        Route::get('/pembayaran', [AuthController::class, 'pembayaranStatusPpdb']);
        Route::put('/form', [AuthController::class, 'updateFormPpdb']);
        Route::post('/pengumuman/cek', [AuthController::class, 'cekPengumumanPpdb']);
    });

    Route::prefix('akademik')->group(function () {
        Route::get('/bobot', [BobotNilaiController::class, 'index']);
        Route::post('/bobot', [BobotNilaiController::class, 'store']);
        Route::post('/bobot/set-default', [BobotNilaiController::class, 'setDefault']);
        Route::get('/bobot/{id}', [BobotNilaiController::class, 'show']);
        Route::put('/bobot/{id}', [BobotNilaiController::class, 'update']);
        Route::delete('/bobot/{id}', [BobotNilaiController::class, 'destroy']);

        Route::get('/kkm-mapel', [KkmMapelController::class, 'index']);
        Route::post('/kkm-mapel', [KkmMapelController::class, 'store']);
        Route::get('/kkm-mapel/{id}', [KkmMapelController::class, 'show']);
        Route::put('/kkm-mapel/{id}', [KkmMapelController::class, 'update']);
        Route::delete('/kkm-mapel/{id}', [KkmMapelController::class, 'destroy']);

        Route::get('/konversi-nilai', [KonversiNilaiController::class, 'index']);
        Route::post('/konversi-nilai', [KonversiNilaiController::class, 'store']);
        Route::get('/konversi-nilai/{id}', [KonversiNilaiController::class, 'show']);
        Route::put('/konversi-nilai/{id}', [KonversiNilaiController::class, 'update']);
        Route::delete('/konversi-nilai/{id}', [KonversiNilaiController::class, 'destroy']);

        Route::get('/nilai-mapel', [NilaiMapelController::class, 'index']);
        Route::post('/nilai-mapel', [NilaiMapelController::class, 'upsert']);
        Route::get('/nilai-mapel/{kode_mapel}', [NilaiMapelController::class, 'show']);
        Route::put('/nilai-mapel/{id}', [NilaiMapelController::class, 'update']);
        Route::delete('/nilai-mapel/{id}', [NilaiMapelController::class, 'destroy']);

        Route::prefix('nilai-statistik')->group(function () {
            Route::get('/', [NilaiStatistikController::class, 'index']);
            Route::get('/per-kelas', [NilaiStatistikController::class, 'averagePerClass']);
            Route::get('/trend', [NilaiStatistikController::class, 'trendPerSemester']);
            Route::get('/berprestasi', [NilaiStatistikController::class, 'topPerformers']);
            Route::get('/perlu-bimbingan', [NilaiStatistikController::class, 'needsHelp']);
        });

        Route::get('/nilai-akhlak', [NilaiAkhlakController::class, 'index']);
        Route::get('/nilai-akhlak/bar', [NilaiAkhlakController::class, 'bar']);
        Route::post('/nilai-akhlak', [NilaiAkhlakController::class, 'upsert']);
        Route::delete('/nilai-akhlak/{id}', [NilaiAkhlakController::class, 'destroy']);

        Route::get('/raport/keseharian', [RaportKeseharianController::class, 'index']);
        Route::post('/raport/keseharian', [RaportKeseharianController::class, 'upsert']);

        Route::get('/raport/catatan-wali', [RaportCatatanWaliController::class, 'show']);
        Route::post('/raport/catatan-wali', [RaportCatatanWaliController::class, 'upsert']);

        Route::get('/raport', [RaportGenerateController::class, 'index']);
        Route::get('/raport/show', [RaportGenerateController::class, 'show']);
        Route::post('/raport/generate', [RaportGenerateController::class, 'generate']);
        Route::post('/raport/rank', [RaportGenerateController::class, 'rank']);
        Route::post('/rangking-kelas/generate', [RangkingKelasController::class, 'generate']);
        Route::post('/raport/publish', [RaportGenerateController::class, 'publish']);
        Route::post('/raport/tarik', [RaportGenerateController::class, 'tarik']);
        Route::get('/raport/pdf', [RaportPdfController::class, 'download']);
        Route::get('/raport/self', [RaportPdfController::class, 'selfShow']);
        Route::get('/raport/self/pdf', [RaportPdfController::class, 'selfDownload']);

        Route::prefix('santri')->group(function () {
            Route::get('/', [DataSantriController::class, 'index']);
            Route::post('/', [DataSantriController::class, 'store']);
            Route::get('/trash', [DataSantriController::class, 'trash']);
            Route::get('/{id}/dependency-summary', [DataSantriController::class, 'dependencySummary']);
            Route::post('/{id}/restore', [DataSantriController::class, 'restore']);
            Route::delete('/{id}/force', [DataSantriController::class, 'forceDelete']);
            Route::post('/pindah-kelas', [DataSantriController::class, 'pindahKelas']);
            Route::post('/bulk-lulus', [DataSantriController::class, 'bulkLulus']);
            Route::post('/batal-lulus', [DataSantriController::class, 'batalLulus']);
            Route::post('/{id}/buat-akun', [DataSantriController::class, 'buatAkun']);
            Route::post('/import', [DataSantriController::class, 'import']);
            Route::get('/export', [DataSantriController::class, 'export']);
            Route::get('/import-template', [DataSantriController::class, 'importTemplate']);
            Route::get('/{id}', [DataSantriController::class, 'show']);
            Route::put('/{id}', [DataSantriController::class, 'update']);
            Route::delete('/{id}', [DataSantriController::class, 'destroy']);
        });

        Route::prefix('akun-santri')->group(function () {
            Route::get('/kelas-tanpa-akun', [DataAkunSantriController::class, 'kelasTanpaAkun']);
            Route::get('/santri-tanpa-akun', [DataAkunSantriController::class, 'santriTanpaAkunByKelas']);
            Route::post('/sinkron', [DataAkunSantriController::class, 'sinkron']);
            Route::get('/', [DataAkunSantriController::class, 'index']);
            Route::post('/', [DataAkunSantriController::class, 'store']);
            Route::get('/export', [DataAkunSantriController::class, 'export']);
            Route::get('/{id}', [DataAkunSantriController::class, 'show']);
            Route::put('/{id}', [DataAkunSantriController::class, 'update']);
            Route::delete('/{id}', [DataAkunSantriController::class, 'destroy']);
        });

        Route::prefix('petugas')->group(function () {
            Route::get('/peran-akun-options', [DataPetugasController::class, 'peranAkunOptions']);
            Route::get('/', [DataPetugasController::class, 'index']);
            Route::post('/', [DataPetugasController::class, 'store']);
            Route::post('/import', [DataPetugasController::class, 'import']);
            Route::get('/export', [DataPetugasController::class, 'export']);
            Route::get('/import-template', [DataPetugasController::class, 'importTemplate']);
            Route::get('/{id}', [DataPetugasController::class, 'show']);
            Route::put('/{id}', [DataPetugasController::class, 'update']);
            Route::delete('/{id}', [DataPetugasController::class, 'destroy']);
        });

        Route::prefix('unit')->group(function () {
            Route::get('/', [DataUnitController::class, 'index']);
            Route::post('/', [DataUnitController::class, 'store']);
            Route::post('/import', [DataUnitController::class, 'import']);
            Route::get('/export', [DataUnitController::class, 'export']);
            Route::get('/{id}', [DataUnitController::class, 'show']);
            Route::put('/{id}', [DataUnitController::class, 'update']);
            Route::delete('/{id}', [DataUnitController::class, 'destroy']);
        });

        Route::prefix('kelas')->group(function () {
            Route::get('/', [DataKelasController::class, 'index']);
            Route::get('/trash', [DataKelasController::class, 'trash']);
            Route::post('/', [DataKelasController::class, 'store']);
            Route::post('/import', [DataKelasController::class, 'import']);
            Route::get('/export', [DataKelasController::class, 'export']);
            Route::get('/import-template', [DataKelasController::class, 'importTemplate']);
            Route::get('/{id}/dependency-summary', [DataKelasController::class, 'dependencySummary']);
            Route::post('/{id}/restore', [DataKelasController::class, 'restore']);
            Route::delete('/{id}/force', [DataKelasController::class, 'forceDelete']);
            Route::get('/{id}', [DataKelasController::class, 'show']);
            Route::put('/{id}', [DataKelasController::class, 'update']);
            Route::delete('/{id}', [DataKelasController::class, 'destroy']);
        });

        Route::prefix('mata-pelajaran')->group(function () {
            Route::get('/', [DataMataPelajaranController::class, 'index']);
            Route::post('/', [DataMataPelajaranController::class, 'store']);
            Route::post('/import', [DataMataPelajaranController::class, 'import']);
            Route::get('/export', [DataMataPelajaranController::class, 'export']);
            Route::get('/import-template', [DataMataPelajaranController::class, 'importTemplate']);
            Route::get('/{id}', [DataMataPelajaranController::class, 'show']);
            Route::put('/{id}', [DataMataPelajaranController::class, 'update']);
            Route::delete('/{id}', [DataMataPelajaranController::class, 'destroy']);
        });

        Route::prefix('kelas-mapel')->group(function () {
            Route::get('/', [DataKelasMapelController::class, 'index']);
            Route::post('/', [DataKelasMapelController::class, 'store']);
            Route::post('/import', [DataKelasMapelController::class, 'import']);
            Route::get('/export', [DataKelasMapelController::class, 'export']);
            Route::get('/import-template', [DataKelasMapelController::class, 'importTemplate']);
            Route::get('/{id}', [DataKelasMapelController::class, 'show']);
            Route::put('/{id}', [DataKelasMapelController::class, 'update']);
            Route::delete('/{id}', [DataKelasMapelController::class, 'destroy']);
        });

        Route::prefix('jadwal-pembelajaran')->group(function () {
            Route::get('/', [DataJadwalPembelajaranController::class, 'index']);
            Route::get('/by-nomor-induk/{nomor_induk}', [DataJadwalPembelajaranController::class, 'byNomorInduk']);
            Route::post('/', [DataJadwalPembelajaranController::class, 'store']);
            Route::post('/import', [DataJadwalPembelajaranController::class, 'import']);
            Route::get('/export', [DataJadwalPembelajaranController::class, 'export']);
            Route::get('/import-template', [DataJadwalPembelajaranController::class, 'importTemplate']);
            Route::get('/{id}', [DataJadwalPembelajaranController::class, 'show']);
            Route::put('/{id}', [DataJadwalPembelajaranController::class, 'update']);
            Route::delete('/{id}', [DataJadwalPembelajaranController::class, 'destroy']);
        });

        Route::prefix('sesi-absensi')->group(function () {
            Route::get('/', [SesiAbsensiController::class, 'index']);
            Route::get('/aktif', [SesiAbsensiController::class, 'aktif']);
            Route::post('/admin/buka-sesi', [AdminSesiAbsensiController::class, 'bukaSesi']);
            Route::get('/admin/belum-diabsen', [AdminSesiAbsensiController::class, 'belumDiabsen']);
            
            Route::get('/rekap/santri', [RekapAbsensiController::class, 'rekapSantri']);
            Route::get('/rekap/santri/export', [RekapAbsensiController::class, 'exportSantri']);
            Route::get('/rekap/kelas', [RekapAbsensiController::class, 'rekapKelas']);
            Route::get('/rekap/kelas/export', [RekapAbsensiController::class, 'exportKelas']);
            Route::get('/rekap/petugas', [RekapAbsensiController::class, 'rekapPetugas']);
            Route::get('/rekap/petugas/export', [RekapAbsensiController::class, 'exportPetugas']);
            Route::get('/riwayat-santri', [SesiAbsensiController::class, 'riwayatSantri']);
            
            Route::post('/mulai', [SesiAbsensiController::class, 'mulai']);
            Route::post('/{id}/set-pengganti', [SesiAbsensiController::class, 'setPengganti']);
            Route::post('/{id}/cancel', [SesiAbsensiController::class, 'cancel']);
            Route::get('/{id}/santri', [SesiAbsensiController::class, 'daftarSantri']);
            Route::post('/{id}/absensi-santri', [SesiAbsensiController::class, 'inputAbsensiSantri']);
            
            Route::put('/{id}/admin/absensi-petugas', [AdminSesiAbsensiController::class, 'upsertAbsensiPengajar']);
            Route::delete('/{id}/admin/absensi-petugas', [AdminSesiAbsensiController::class, 'deleteAbsensiPengajar']);
            Route::put('/{id}/admin/absensi-santri', [AdminSesiAbsensiController::class, 'upsertAbsensiSantri']);
            Route::delete('/{id}/admin/absensi-santri', [AdminSesiAbsensiController::class, 'deleteAbsensiSantri']);
            
            Route::post('/{id}/selesai', [SesiAbsensiController::class, 'selesai']);
            Route::get('/{id}', [SesiAbsensiController::class, 'show']);
        });

        Route::get('/presensi/overview', [DashboardPresensiController::class, 'overviewHarian']);

        Route::prefix('tahun-ajaran')->group(function () {
            Route::get('/', [DataTahunAjaranController::class, 'index']);
            Route::post('/', [DataTahunAjaranController::class, 'store']);
            Route::post('/import', [DataTahunAjaranController::class, 'import']);
            Route::get('/export', [DataTahunAjaranController::class, 'export']);
            Route::get('/import-template', [DataTahunAjaranController::class, 'importTemplate']);
            Route::get('/{id}', [DataTahunAjaranController::class, 'show']);
            Route::put('/{id}', [DataTahunAjaranController::class, 'update']);
            Route::delete('/{id}', [DataTahunAjaranController::class, 'destroy']);
        });
    });
});

Route::prefix('administrasi')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('ppdb')->group(function () {
        Route::get('/pendaftar/rekap/diterima', [PpdbController::class, 'rekapDiterima']);
        Route::get('/pendaftar/export', [PpdbController::class, 'export']);
        Route::get('/pendaftar', [PpdbController::class, 'index']);
        Route::post('/pendaftar', [PpdbController::class, 'store']);
        Route::get('/pendaftar/{id}', [PpdbController::class, 'show']);
        Route::put('/pendaftar/{id}', [PpdbController::class, 'update']);
        Route::delete('/pendaftar/{id}', [PpdbController::class, 'destroy']);

        Route::post('/pendaftar/{id}/berkas', [PpdbController::class, 'storeBerkas']);
        Route::put('/pendaftar/{id}/tes', [PpdbController::class, 'upsertTes']);
        Route::put('/pendaftar/{id}/verifikasi', [PpdbController::class, 'upsertVerifikasi']);
        Route::post('/pendaftar/{id}/notifikasi', [PpdbController::class, 'storeNotifikasi']);
        Route::post('/pendaftar/{id}/tagihan', [PpdbController::class, 'createTagihanPpdb']);

        Route::get('/tes/konfigurasi', [PpdbTesKonfigurasiController::class, 'index']);
        Route::put('/tes/konfigurasi/{jenjang}', [PpdbTesKonfigurasiController::class, 'update']);
    });

    Route::prefix('pengumuman')->group(function () {
        Route::get('/', [PengumumanController::class, 'index']);
        Route::post('/', [PengumumanController::class, 'store']);
        Route::get('/{id}', [PengumumanController::class, 'show']);
        Route::put('/{id}', [PengumumanController::class, 'update']);
        Route::delete('/{id}', [PengumumanController::class, 'destroy']);
    });

    Route::prefix('spp')->group(function () {
        Route::get('/setting', [SppSettingController::class, 'index']);
        Route::get('/setting/kelas', [SppSettingController::class, 'kelasIndex']);
        Route::post('/setting', [SppSettingController::class, 'store']);
        Route::get('/setting/{id}', [SppSettingController::class, 'show']);
        Route::put('/setting/{id}', [SppSettingController::class, 'update']);
        Route::delete('/setting/{id}', [SppSettingController::class, 'destroy']);
        Route::post('/setting/{id}/generate', [SppSettingController::class, 'generateTagihanPeriode']);

        // Provision bills endpoint - allows manual triggering of bill generation
        Route::post('/provision-bills', [SppSettingController::class, 'provisionBills']);

        Route::get('/golongan', [SppGolonganController::class, 'index']);
        Route::post('/golongan', [SppGolonganController::class, 'store']);
        Route::get('/golongan/{id}', [SppGolonganController::class, 'show']);
        Route::put('/golongan/{id}', [SppGolonganController::class, 'update']);
        Route::delete('/golongan/{id}', [SppGolonganController::class, 'destroy']);

        Route::get('/pembayaran', [PembayaranSppController::class, 'index']);
        Route::post('/pembayaran', [PembayaranSppController::class, 'store']);
        Route::put('/pembayaran/{id}/verifikasi', [PembayaranSppController::class, 'verifikasiPembayaran']);
        Route::get('/pembayaran/{id}', [PembayaranSppController::class, 'show']);
        Route::put('/pembayaran/{id}', [PembayaranSppController::class, 'update']);
        Route::delete('/pembayaran/{id}', [PembayaranSppController::class, 'destroy']);
        Route::get('/pembayaran/{id}/kwitansi', [PembayaranSppController::class, 'downloadKwitansi']);

        Route::get('/tunggakan-ringkasan', [PembayaranSppController::class, 'tunggakanRingkasan']);
    });

    Route::prefix('bebas')->group(function () {
        Route::get('/', [AdministrasiBebasController::class, 'index']);
        Route::post('/', [AdministrasiBebasController::class, 'store']);
        Route::get('/{id}', [AdministrasiBebasController::class, 'show']);
        Route::put('/{id}', [AdministrasiBebasController::class, 'update']);
        Route::delete('/{id}', [AdministrasiBebasController::class, 'destroy']);
        Route::post('/{id}/pembayaran', [AdministrasiBebasController::class, 'storePembayaran']);
        Route::get('/kwitansi/{idKwitansi}', [AdministrasiBebasController::class, 'downloadKwitansi']);
    });

    Route::prefix('pembayaran')->group(function () {
        Route::get('/options', [PembayaranController::class, 'options']);
        Route::get('/tagihan', [PembayaranController::class, 'tagihan']);
        Route::get('/tagihan/{id}/detail', [PembayaranController::class, 'tagihanDetail']);
        Route::get('/proses', [PembayaranController::class, 'proses']);
        Route::get('/verifikasi', [PembayaranController::class, 'verifikasi']);
        Route::get('/', [PembayaranController::class, 'index']);
        Route::get('/ringkasan', [PembayaranController::class, 'ringkasan']);
        Route::get('/{id}/detail', [PembayaranController::class, 'detail']);
        Route::post('/{id}/upload-bukti', [PembayaranController::class, 'uploadBuktiBayar']);
        Route::put('/{id}/konfirmasi', [PembayaranController::class, 'konfirmasiVerifikasi']);
        Route::put('/{id}/status', [PembayaranSppController::class, 'updateStatusVerifikasi']);
        Route::delete('/{id}', [PembayaranSppController::class, 'destroy']);
    });
});

Route::prefix('master')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('data-santri')->group(function () {
        Route::get('/options', [DataSantriController::class, 'options']);
    });
});
