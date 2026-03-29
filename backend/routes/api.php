<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Akademik\BobotNilaiController;
use App\Http\Controllers\Api\Akademik\KkmMapelController;
use App\Http\Controllers\Api\Akademik\KonversiNilaiController;
use App\Http\Controllers\Api\Akademik\NilaiAkhlakController;
use App\Http\Controllers\Api\Akademik\RaportCatatanWaliController;
use App\Http\Controllers\Api\Akademik\RaportKeseharianController;
use App\Http\Controllers\Api\Administrasi\DataSantriController;
use App\Http\Controllers\Api\Administrasi\DataPetugasController;
use App\Http\Controllers\Api\Administrasi\PembayaranSppController;
use App\Http\Controllers\Api\Administrasi\PpdbController;
use App\Http\Controllers\Api\Administrasi\SppSettingController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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

        Route::get('/nilai-akhlak', [NilaiAkhlakController::class, 'index']);
        Route::post('/nilai-akhlak', [NilaiAkhlakController::class, 'upsert']);

        Route::get('/raport/keseharian', [RaportKeseharianController::class, 'index']);
        Route::post('/raport/keseharian', [RaportKeseharianController::class, 'upsert']);

        Route::get('/raport/catatan-wali', [RaportCatatanWaliController::class, 'show']);
        Route::post('/raport/catatan-wali', [RaportCatatanWaliController::class, 'upsert']);
    });
});

Route::prefix('administrasi')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('ppdb')->group(function () {
        Route::get('/pendaftar', [PpdbController::class, 'index']);
        Route::post('/pendaftar', [PpdbController::class, 'store']);
        Route::get('/pendaftar/{id}', [PpdbController::class, 'show']);
        Route::put('/pendaftar/{id}', [PpdbController::class, 'update']);
        Route::delete('/pendaftar/{id}', [PpdbController::class, 'destroy']);

        Route::post('/pendaftar/{id}/berkas', [PpdbController::class, 'storeBerkas']);
        Route::put('/pendaftar/{id}/tes', [PpdbController::class, 'upsertTes']);
        Route::put('/pendaftar/{id}/verifikasi', [PpdbController::class, 'upsertVerifikasi']);
        Route::post('/pendaftar/{id}/notifikasi', [PpdbController::class, 'storeNotifikasi']);
    });

    Route::prefix('santri')->group(function () {
        Route::get('/', [DataSantriController::class, 'index']);
        Route::post('/', [DataSantriController::class, 'store']);
        Route::post('/pindah-kelas', [DataSantriController::class, 'pindahKelas']);
        Route::post('/import', [DataSantriController::class, 'import']);
        Route::get('/export', [DataSantriController::class, 'export']);
        Route::get('/import-template', [DataSantriController::class, 'importTemplate']);
        Route::get('/{id}', [DataSantriController::class, 'show']);
        Route::put('/{id}', [DataSantriController::class, 'update']);
        Route::delete('/{id}', [DataSantriController::class, 'destroy']);
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

    Route::prefix('spp')->group(function () {
        Route::get('/setting', [SppSettingController::class, 'index']);
        Route::post('/setting', [SppSettingController::class, 'store']);
        Route::get('/setting/{id}', [SppSettingController::class, 'show']);
        Route::put('/setting/{id}', [SppSettingController::class, 'update']);
        Route::delete('/setting/{id}', [SppSettingController::class, 'destroy']);

        Route::get('/pembayaran', [PembayaranSppController::class, 'index']);
        Route::post('/pembayaran', [PembayaranSppController::class, 'store']);
        Route::get('/pembayaran/{id}', [PembayaranSppController::class, 'show']);
        Route::put('/pembayaran/{id}', [PembayaranSppController::class, 'update']);
        Route::delete('/pembayaran/{id}', [PembayaranSppController::class, 'destroy']);
    });
});
