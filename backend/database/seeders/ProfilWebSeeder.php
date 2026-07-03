<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfilWeb;

class ProfilWebSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $profiles = [
            [
                'tipe' => 'PAUD',
                'nama' => 'PAUD & TK Al Ausath',
                'visi' => "Mewujudkan generasi usia dini yang ceria, sehat, dan cinta Al-Qur'an.",
                'misi' => [
                    "Menanamkan aqidah dan akhlak mulia sejak dini",
                    "Melatih kemandirian dan motorik anak melalui bermain bermakna",
                    "Mengenalkan dasar-dasar membaca Al-Qur'an dan doa harian"
                ],
                'sejarah' => "PAUD dan TK Al Ausath didirikan sebagai langkah awal pesantren dalam membina tunas bangsa. Dengan metode pendidikan yang menyenangkan, kami berkomitmen menjadi mitra terbaik orang tua dalam fase golden age anak.",
                'program_unggulan' => [
                    "Bermain sambil belajar nilai Islam",
                    "Hafalan surat pendek & doa harian",
                    "Pengembangan kreativitas seni & motorik"
                ]
            ],
            [
                'tipe' => 'MI',
                'nama' => 'Madrasah Ibtidaiyah (MI)',
                'visi' => "Mewujudkan generasi dasar yang Qur'ani, berakhlak mulia, dan berprestasi.",
                'misi' => [
                    "Menyelenggarakan pendidikan dasar berbasis Al-Qur'an dan As-Sunnah",
                    "Membiasakan adab dan akhlakul karimah sejak dini",
                    "Mengembangkan kemampuan dasar calistung dan tahfidz juz 30"
                ],
                'sejarah' => "MI Al Ausath hadir untuk merespon tingginya minat masyarakat terhadap pendidikan dasar yang mengintegrasikan kurikulum nasional dengan ilmu agama secara intensif.",
                'program_unggulan' => [
                    "Pembelajaran tematik terpadu",
                    "Ekstrakurikuler pramuka & tahfidz",
                    "Pembiasaan sholat dhuha & dzuhur berjamaah"
                ]
            ],
            [
                'tipe' => 'MTS',
                'nama' => 'Madrasah Tsanawiyah (MTs)',
                'visi' => "Menjadi lembaga pendidikan menengah yang unggul dalam IPTEK dan IMTAQ.",
                'misi' => [
                    "Mengintegrasikan kurikulum nasional dengan kepesantrenan",
                    "Mencetak generasi penghafal Al-Qur'an (Target 3 Juz)",
                    "Membekali santri dengan kemampuan bahasa Arab dan Inggris"
                ],
                'sejarah' => "MTs Al Ausath merupakan wadah lanjutan bagi santri usia remaja awal untuk mendalami ilmu agama dan sains secara komprehensif, ditunjang dengan fasilitas pesantren yang memadai.",
                'program_unggulan' => [
                    "Kajian kitab kuning dasar",
                    "English & Arabic club",
                    "Pelatihan kepemimpinan santri"
                ]
            ],
            [
                'tipe' => 'MA',
                'nama' => 'Madrasah Aliyah (MA)',
                'visi' => "Mencetak lulusan yang siap bersaing global dengan landasan akidah yang lurus.",
                'misi' => [
                    "Mempersiapkan santri menembus Perguruan Tinggi Negeri & Timur Tengah",
                    "Meningkatkan kualitas tahfidz (Target 5 Juz) dan pemahaman agama",
                    "Mengembangkan jiwa kemandirian dan kewirausahaan"
                ],
                'sejarah' => "MA Al Ausath didirikan sebagai jenjang puncak pendidikan menengah pesantren, berfokus pada kematangan intelektual, kemandirian spiritual, dan persiapan karir atau studi lanjut santri.",
                'program_unggulan' => [
                    "Bimbingan intensif UTBK/SNBT",
                    "Kajian kitab (Takhassus)",
                    "Program pengabdian masyarakat"
                ]
            ]
        ];

        foreach ($profiles as $profile) {
            ProfilWeb::updateOrCreate(
                ['tipe' => $profile['tipe']],
                $profile
            );
        }
    }
}
