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
        $konfigurasi = PpdbTesKonfigurasi::all()->map(function ($config) {
            $soalTesRaw = trim((string) ($config->soal_tes ?? ''));
            $soalTesDecoded = json_decode($soalTesRaw, true);
            $config->soal_tes = (json_last_error() === JSON_ERROR_NONE) ? $soalTesDecoded : $soalTesRaw;
            return $config;
        });

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

        if ($request->has('soal_tes') && is_array($request->input('soal_tes'))) {
            $request->merge(['soal_tes' => json_encode($request->input('soal_tes'), JSON_UNESCAPED_UNICODE)]);
        }

        $validated = $request->validate([
            'fitur_soal_aktif' => ['nullable', 'boolean'],
            'bahasa' => ['nullable', 'string', 'in:id,ar'],
            'is_rtl' => ['nullable', 'boolean'],
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
                'bahasa' => array_key_exists('bahasa', $validated)
                    ? $validated['bahasa']
                    : ($existing?->bahasa ?? 'id'),
                'is_rtl' => array_key_exists('is_rtl', $validated)
                    ? (bool) $validated['is_rtl']
                    : (bool) ($existing?->is_rtl ?? false),
                'soal_tes' => array_key_exists('soal_tes', $validated)
                    ? $validated['soal_tes']
                    : ($existing?->soal_tes),
                'form_schema' => array_key_exists('form_schema', $validated)
                    ? $validated['form_schema']
                    : ($existing?->form_schema),
            ]
        );

        $soalTesRaw = trim((string) ($konfigurasi->soal_tes ?? ''));
        $soalTesDecoded = json_decode($soalTesRaw, true);
        $konfigurasi->soal_tes = (json_last_error() === JSON_ERROR_NONE) ? $soalTesDecoded : $soalTesRaw;

        return response()->json([
            'message' => 'Konfigurasi tes berhasil diperbarui',
            'data' => $konfigurasi
        ]);
    }

    /**
     * Upload gambar pendukung soal ujian PPDB.
     */
    public function uploadGambar(Request $request): JsonResponse
    {
        $request->validate([
            'gambar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('gambar')) {
            $file = $request->file('gambar');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', $originalName);
            $fileName = $safeName . '_' . time() . '.' . $extension;
            $filePath = $file->storeAs('ppdb/tes_soal', $fileName, 'public');

            return response()->json([
                'message' => 'Gambar berhasil diupload',
                'file_path' => $filePath,
                'file_url' => \Illuminate\Support\Facades\Storage::url($filePath),
            ], 200);
        }

        return response()->json([
            'message' => 'Gagal mengupload gambar',
        ], 422);
    }
}
