<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Administrasi\DataSantriController;
use App\Http\Controllers\Api\Administrasi\PembayaranSppController;
use App\Http\Controllers\Api\Administrasi\PpdbController;
use App\Http\Controllers\Api\Administrasi\SppSettingController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::prefix('administrasi')->group(function () {
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
        Route::get('/{id}', [DataSantriController::class, 'show']);
        Route::put('/{id}', [DataSantriController::class, 'update']);
        Route::delete('/{id}', [DataSantriController::class, 'destroy']);
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