<?php

namespace Database\Seeders;

use App\Models\DataPetugas;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PetugasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DataPetugas::updateOrCreate(
            ['nomor_induk' => '999999'],
            [
                'nama_lengkap' => 'Admin Utama',
                'peran_akun' => ['Petugas Admin'],
                'pilihan_unit' => 'Semua Unit',
                'alamat_email' => 'admin@gmail.com',
                'nomor_telepon' => '081234567890',
                'password_hash' => Hash::make('password'),
                'status' => 'Aktif'
            ]
        );
    }
}
