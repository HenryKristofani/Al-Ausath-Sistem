<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\DataPetugas;
use App\Models\DataAkunSantri;
use App\Models\DataSantri;

class ProfileController extends Controller
{
    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:6|confirmed',
        ]);

        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Cek password lama
        if (!Hash::check($request->password_lama, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'password_lama' => ['Password lama tidak sesuai.'],
            ]);
        }

        // Update password baru
        $user->password_hash = Hash::make($request->password_baru);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }

    public function updateBiodata(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'nama_lengkap' => 'required|string|max:200',
            'alamat_email' => 'nullable|email|max:150',
            'nomor_telepon' => 'nullable|string|max:30',
        ]);

        if ($user instanceof DataPetugas) {
            // Cek keunikan email/telepon untuk petugas (kecuali diri sendiri)
            $request->validate([
                'alamat_email' => 'nullable|email|unique:data_petugas,alamat_email,' . $user->id_petugas . ',id_petugas',
                'nomor_telepon' => 'nullable|string|unique:data_petugas,nomor_telepon,' . $user->id_petugas . ',id_petugas',
            ]);

            $user->nama_lengkap = $request->nama_lengkap;
            $user->alamat_email = $request->alamat_email;
            $user->nomor_telepon = $request->nomor_telepon;
            $user->save();
        } elseif ($user instanceof DataAkunSantri) {
            $request->validate([
                'jenis_kelamin' => 'nullable|in:L,P',
            ]);
            
            // Update akun santri
            $user->nama_lengkap = $request->nama_lengkap;
            $user->alamat_email = $request->alamat_email;
            $user->nomor_telepon = $request->nomor_telepon;
            $user->save();

            // Sync dengan tabel biodata master (DataSantri) jika terhubung
            $santri = DataSantri::where('nomor_induk', $user->nomor_induk)->first();
            if ($santri) {
                $santri->nama_lengkap_santri = $request->nama_lengkap;
                $santri->alamat_email = $request->alamat_email;
                $santri->nomor_telepon = $request->nomor_telepon;
                if ($request->has('jenis_kelamin')) {
                    $santri->jenis_kelamin = $request->jenis_kelamin;
                }
                $santri->save();
            }
        } else {
            return response()->json(['message' => 'Tipe user tidak didukung.'], 400);
        }

        return response()->json([
            'message' => 'Biodata berhasil diperbarui.',
            'user' => [
                'nama_lengkap' => $user->nama_lengkap,
                'alamat_email' => $user->alamat_email,
                'nomor_telepon' => $user->nomor_telepon,
            ]
        ]);
    }
}
