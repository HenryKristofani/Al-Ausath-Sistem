<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfilWeb;
use Illuminate\Http\Request;

class ProfilWebController extends Controller
{
    /**
     * Display a listing of the resource for public/landing page.
     * Termasuk artikel_url agar navbar landing page bisa baca URL artikel pesantren.
     */
    public function index()
    {
        $profil = ProfilWeb::all();
        return response()->json(['data' => $profil]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipe'             => 'required|string|unique:profil_web,tipe',
            'nama'             => 'required|string',
            'lama_pendidikan'  => 'nullable|string',
            'visi'             => 'nullable|string',
            'misi'             => 'nullable|array',
            'sejarah'          => 'nullable|string',
            'program_unggulan' => 'nullable|array',
            'fasilitas'        => 'nullable|array',
            'artikel_url'      => 'nullable|url|max:500',
        ]);

        $profil = ProfilWeb::create($validated);
        return response()->json(['message' => 'Profil berhasil ditambahkan', 'data' => $profil], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id_profil)
    {
        $profil = ProfilWeb::findOrFail($id_profil);
        return response()->json(['data' => $profil]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id_profil)
    {
        $profil = ProfilWeb::findOrFail($id_profil);

        $validated = $request->validate([
            'tipe'             => 'required|string|unique:profil_web,tipe,' . $id_profil . ',id_profil',
            'nama'             => 'required|string',
            'lama_pendidikan'  => 'nullable|string',
            'visi'             => 'nullable|string',
            'misi'             => 'nullable|array',
            'sejarah'          => 'nullable|string',
            'program_unggulan' => 'nullable|array',
            'fasilitas'        => 'nullable|array',
            'artikel_url'      => 'nullable|url|max:500',
        ]);

        $profil->update($validated);
        return response()->json(['message' => 'Profil berhasil diperbarui', 'data' => $profil]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id_profil)
    {
        $profil = ProfilWeb::findOrFail($id_profil);
        $profil->delete();
        return response()->json(['message' => 'Profil berhasil dihapus']);
    }
}
