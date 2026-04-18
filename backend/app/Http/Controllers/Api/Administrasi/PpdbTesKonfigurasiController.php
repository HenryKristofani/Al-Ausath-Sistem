<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\PpdbTesKonfigurasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PpdbTesKonfigurasiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get all configuration settings.
     */
    public function index(): JsonResponse
    {
        $konfigurasi = PpdbTesKonfigurasi::all();

        return response()->json([
            'message' => 'success',
            'data' => $konfigurasi
        ]);
    }

    /**
     * Update configuration for a specific jenjang.
     */
    public function update(Request $request, string $jenjang): JsonResponse
    {
        $validated = $request->validate([
            'fitur_soal_aktif' => ['nullable', 'boolean'],
            'soal_tes' => ['nullable', 'string'],
            'form_schema' => ['nullable', 'array'],
        ]);

        $konfigurasi = PpdbTesKonfigurasi::updateOrCreate(
            ['jenjang' => strtoupper($jenjang)],
            [
                'fitur_soal_aktif' => $validated['fitur_soal_aktif'] ?? false,
                'soal_tes' => $validated['soal_tes'] ?? null,
                'form_schema' => $validated['form_schema'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Konfigurasi tes berhasil diperbarui',
            'data' => $konfigurasi
        ]);
    }
}
