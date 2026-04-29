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
        if ($request->has('fitur_soal_aktif') && is_string($request->input('fitur_soal_aktif'))) {
            $rawToggle = mb_strtolower(trim((string) $request->input('fitur_soal_aktif')));
            if (in_array($rawToggle, ['on', 'aktif', 'true', '1', 'yes'], true)) {
                $request->merge(['fitur_soal_aktif' => true]);
            } elseif (in_array($rawToggle, ['off', 'nonaktif', 'false', '0', 'no'], true)) {
                $request->merge(['fitur_soal_aktif' => false]);
            }
        }

        $validated = $request->validate([
            'fitur_soal_aktif' => ['nullable', 'boolean'],
            'soal_tes' => ['nullable', 'string'],
            'form_schema' => ['nullable', 'array'],
        ]);

        $jenjangKey = strtoupper($jenjang);
        $existing = PpdbTesKonfigurasi::where('jenjang', $jenjangKey)->first();

        $konfigurasi = PpdbTesKonfigurasi::updateOrCreate(
            ['jenjang' => $jenjangKey],
            [
                'fitur_soal_aktif' => array_key_exists('fitur_soal_aktif', $validated)
                    ? (bool) $validated['fitur_soal_aktif']
                    : (bool) ($existing?->fitur_soal_aktif ?? false),
                'soal_tes' => array_key_exists('soal_tes', $validated)
                    ? $validated['soal_tes']
                    : ($existing?->soal_tes),
                'form_schema' => array_key_exists('form_schema', $validated)
                    ? $validated['form_schema']
                    : ($existing?->form_schema),
            ]
        );

        return response()->json([
            'message' => 'Konfigurasi tes berhasil diperbarui',
            'data' => $konfigurasi
        ]);
    }
}
