<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\DataPetugas;
use App\Models\DataUnit;
use App\Models\DataTahunAjaran;
use App\Models\DataKelas;
use App\Models\DataMataPelajaran;
use App\Models\DataKelasMapel;
use App\Models\JadwalPembelajaran;

class AkademikJadwalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create or update Petugas 'guru@gmail.com'
        $guru = DataPetugas::updateOrCreate(
            ['alamat_email' => 'guru@gmail.com'],
            [
                'nomor_induk' => '100002',
                'nama_lengkap' => 'Ustadz Ahmad Hidayat, S.Pd.',
                'peran_akun' => ['Staf Pengajar'],
                'pilihan_unit' => 'Semua Unit',
                'nomor_telepon' => '081234567891',
                'password_hash' => Hash::make('password'),
                'status' => 'Aktif',
            ]
        );

        // Also ensure admin@gmail.com exists
        DataPetugas::updateOrCreate(
            ['alamat_email' => 'admin@gmail.com'],
            [
                'nomor_induk' => '999999',
                'nama_lengkap' => 'Admin Utama',
                'peran_akun' => ['Petugas Admin'],
                'pilihan_unit' => 'Semua Unit',
                'nomor_telepon' => '081234567890',
                'password_hash' => Hash::make('password'),
                'status' => 'Aktif',
            ]
        );

        // 2. Ensure DataUnit exists
        $unitSmp = DataUnit::updateOrCreate(
            ['kode_unit' => 'SMP'],
            [
                'nama_unit' => 'SMP Al-Ausath',
                'keterangan' => 'Unit Sekolah Menengah Pertama',
                'status' => 'AKTIF',
            ]
        );

        DataUnit::updateOrCreate(
            ['kode_unit' => 'SMA'],
            [
                'nama_unit' => 'SMA Al-Ausath',
                'keterangan' => 'Unit Sekolah Menengah Atas',
                'status' => 'AKTIF',
            ]
        );

        // 3. Ensure DataTahunAjaran exists
        $tahunAjaran = DataTahunAjaran::updateOrCreate(
            ['kode_tahun' => '2024/2025'],
            [
                'nama_tahun' => 'Tahun Ajaran 2024/2025',
                'keterangan' => 'Tahun Ajaran Aktif',
                'status' => 'AKTIF',
                'is_deleted' => false,
            ]
        );

        // 4. Ensure DataKelas exists
        $kelas7a = DataKelas::updateOrCreate(
            ['kode_kelas' => '7A'],
            [
                'nama_kelas' => 'Kelas 7A',
                'kode_unit' => 'SMP',
                'tahun_ajaran' => '2024/2025',
                'status' => 'AKTIF',
                'id_wali_kelas' => $guru->id_petugas,
                'is_deleted' => false,
            ]
        );

        $kelas8a = DataKelas::updateOrCreate(
            ['kode_kelas' => '8A'],
            [
                'nama_kelas' => 'Kelas 8A',
                'kode_unit' => 'SMP',
                'tahun_ajaran' => '2024/2025',
                'status' => 'AKTIF',
                'id_wali_kelas' => $guru->id_petugas,
                'is_deleted' => false,
            ]
        );

        // 5. Create Mata Pelajaran
        $mapelsData = [
            ['kode_mapel' => 'BA-7', 'nama_mapel' => 'Bahasa Arab', 'kelompok_mapel' => 'Agama'],
            ['kode_mapel' => 'FQ-7', 'nama_mapel' => 'Fiqih', 'kelompok_mapel' => 'Agama'],
            ['kode_mapel' => 'QH-7', 'nama_mapel' => 'Al-Qur\'an Hadits', 'kelompok_mapel' => 'Agama'],
            ['kode_mapel' => 'AA-7', 'nama_mapel' => 'Aqidah Akhlak', 'kelompok_mapel' => 'Agama'],
            ['kode_mapel' => 'MTK-7', 'nama_mapel' => 'Matematika', 'kelompok_mapel' => 'Umum'],
        ];

        foreach ($mapelsData as $m) {
            DataMataPelajaran::updateOrCreate(
                ['kode_mapel' => $m['kode_mapel']],
                [
                    'nama_mapel' => $m['nama_mapel'],
                    'kode_unit' => 'SMP',
                    'kelompok_mapel' => $m['kelompok_mapel'],
                    'status' => 'AKTIF',
                ]
            );
        }

        // 6. Create Kelas Mapel (Hubungan Kelas, Mapel & Pengajar guru@gmail.com)
        $kelasMapelDefs = [
            ['kode_kelas' => '7A', 'kode_mapel' => 'BA-7', 'buku_acuan' => 'Durusul Lughah Vol 1'],
            ['kode_kelas' => '7A', 'kode_mapel' => 'FQ-7', 'buku_acuan' => 'Safinatun Najah'],
            ['kode_kelas' => '7A', 'kode_mapel' => 'QH-7', 'buku_acuan' => 'Tafsir Jalalain'],
            ['kode_kelas' => '8A', 'kode_mapel' => 'AA-7', 'buku_acuan' => 'Taisirul Khalaq'],
            ['kode_kelas' => '8A', 'kode_mapel' => 'MTK-7', 'buku_acuan' => 'Matematika Kelas 8'],
        ];

        $createdKelasMapel = [];
        foreach ($kelasMapelDefs as $def) {
            $km = DataKelasMapel::updateOrCreate(
                [
                    'kode_kelas' => $def['kode_kelas'],
                    'kode_mapel' => $def['kode_mapel'],
                    'tahun_ajaran' => '2024/2025',
                    'semester' => 1,
                ],
                [
                    'id_petugas' => $guru->id_petugas,
                    'buku_acuan' => $def['buku_acuan'],
                    'status' => 'AKTIF',
                ]
            );
            $createdKelasMapel[$def['kode_mapel']] = $km->id_kelas_mapel;
        }

        // 7. Create Jadwal Pembelajaran (termasuk HARI INI)
        $dayMap = [
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'MINGGU',
        ];

        $todayHari = $dayMap[Carbon::now('Asia/Jakarta')->dayOfWeekIso];

        // Ensure schedule for TODAY
        $schedulesForToday = [
            [
                'id_kelas_mapel' => $createdKelasMapel['BA-7'],
                'jam_mulai' => '07:30:00',
                'jam_selesai' => '09:00:00',
                'ruangan' => 'Ruang 7A',
            ],
            [
                'id_kelas_mapel' => $createdKelasMapel['FQ-7'],
                'jam_mulai' => '09:15:00',
                'jam_selesai' => '10:45:00',
                'ruangan' => 'Ruang 7A',
            ],
            [
                'id_kelas_mapel' => $createdKelasMapel['QH-7'],
                'jam_mulai' => '11:00:00',
                'jam_selesai' => '12:30:00',
                'ruangan' => 'Ruang 7A',
            ],
            [
                'id_kelas_mapel' => $createdKelasMapel['AA-7'],
                'jam_mulai' => '13:30:00',
                'jam_selesai' => '15:00:00',
                'ruangan' => 'Ruang 8A',
            ],
        ];

        foreach ($schedulesForToday as $sch) {
            JadwalPembelajaran::updateOrCreate(
                [
                    'id_kelas_mapel' => $sch['id_kelas_mapel'],
                    'tahun_ajaran' => '2024/2025',
                    'hari' => $todayHari,
                    'jam_mulai' => $sch['jam_mulai'],
                ],
                [
                    'jam_selesai' => $sch['jam_selesai'],
                    'ruangan' => $sch['ruangan'],
                    'status' => 'AKTIF',
                ]
            );
        }

        // Add schedules for all other days of the week as well
        $otherDays = array_diff(array_values($dayMap), [$todayHari]);
        foreach ($otherDays as $d) {
            JadwalPembelajaran::updateOrCreate(
                [
                    'id_kelas_mapel' => $createdKelasMapel['BA-7'],
                    'tahun_ajaran' => '2024/2025',
                    'hari' => $d,
                    'jam_mulai' => '07:30:00',
                ],
                [
                    'jam_selesai' => '09:00:00',
                    'ruangan' => 'Ruang 7A',
                    'status' => 'AKTIF',
                ]
            );
            JadwalPembelajaran::updateOrCreate(
                [
                    'id_kelas_mapel' => $createdKelasMapel['MTK-7'],
                    'tahun_ajaran' => '2024/2025',
                    'hari' => $d,
                    'jam_mulai' => '09:15:00',
                ],
                [
                    'jam_selesai' => '10:45:00',
                    'ruangan' => 'Ruang 8A',
                    'status' => 'AKTIF',
                ]
            );
        }
    }
}
