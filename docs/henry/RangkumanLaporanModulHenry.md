# Rangkuman Modul Penjadwalan dan E-Rapor Henry

Dokumen ini disusun hanya dari repo ini, terutama dari `backend/routes/api.php`, controller terkait, model, dan dokumen pendukung di `docs/henry`. Fokusnya adalah dua modul yang paling relevan untuk pelaporan magang: modul penjadwalan pembelajaran dan modul e-rapor.

## 1. Modul Penjadwalan Pembelajaran

### 1.1 Tujuan modul
Modul ini mengelola data jadwal pembelajaran per kelas mapel, tahun ajaran, hari, jam mulai, jam selesai, ruang, dan status. Data jadwal ini bukan hanya data master, tetapi juga dipakai oleh proses absensi sesi pembelajaran.

### 1.2 Komponen utama di repo
- Model: `backend/app/Models/JadwalPembelajaran.php`
- Controller: `backend/app/Http/Controllers/Api/DataMaster/DataJadwalPembelajaranController.php`
- Export: `backend/app/Exports/DataJadwalPembelajaranExport.php`
- Import: `backend/app/Imports/DataJadwalPembelajaranImport.php`
- Route: `backend/routes/api.php` pada prefix `api/akademik/jadwal-pembelajaran`

### 1.3 Struktur data inti
Model `JadwalPembelajaran` memakai tabel `jadwal_pembelajaran` dengan primary key `id_jadwal`. Field yang disimpan lewat controller adalah:
- `id_kelas_mapel`
- `tahun_ajaran`
- `hari`
- `jam_mulai`
- `jam_selesai`
- `ruangan`
- `status`

Relasi yang dipakai adalah `kelasMapel()` ke `DataKelasMapel`.

### 1.4 Endpoint yang tersedia
Semua endpoint berada di middleware `auth:sanctum`.
- `GET /api/akademik/jadwal-pembelajaran`
- `POST /api/akademik/jadwal-pembelajaran`
- `POST /api/akademik/jadwal-pembelajaran/import`
- `GET /api/akademik/jadwal-pembelajaran/export`
- `GET /api/akademik/jadwal-pembelajaran/import-template`
- `GET /api/akademik/jadwal-pembelajaran/{id}`
- `PUT /api/akademik/jadwal-pembelajaran/{id}`
- `DELETE /api/akademik/jadwal-pembelajaran/{id}`

### 1.5 Perilaku teknis controller
- List data memakai eager loading `kelasMapel.kelas`, `kelasMapel.mataPelajaran`, dan `kelasMapel.petugas`.
- Filter list mendukung `id_kelas_mapel`, `tahun_ajaran`, `hari`, `status`, dan keyword `q`.
- Urutan default list adalah `tahun_ajaran`, `hari`, lalu `jam_mulai`.
- Saat simpan dan update, field `hari` dan `status` dinormalisasi ke huruf kapital, sedangkan `tahun_ajaran` dan `ruangan` di-trim.
- Validasi utama memastikan:
  - `id_kelas_mapel` harus ada di `data_kelas_mapel`
  - `tahun_ajaran` harus ada di `data_tahun_ajaran` yang belum dihapus
  - `jam_selesai` harus lebih besar dari `jam_mulai`
  - `status` hanya `AKTIF` atau `NONAKTIF`
- Ada validasi kombinasi unik untuk mencegah duplikasi jadwal pada kombinasi:
  - `id_kelas_mapel`
  - `tahun_ajaran`
  - `hari`
  - `jam_mulai`
- `DELETE` akan menolak penghapusan jika jadwal masih dipakai oleh data terkait dan akan mengembalikan pesan error 422.

### 1.6 Import, export, dan template
- Import mendukung CSV, TXT, XLSX, dan XLS.
- Jika file Excel dipakai, import memanfaatkan `DataJadwalPembelajaranImport`.
- Jika CSV dipakai, controller membaca header lalu memetakan baris secara manual.
- Logika import bersifat upsert berbasis kombinasi unik jadwal.
- Export menghasilkan file Excel `data-jadwal-pembelajaran-YYYYMMDD_HHMMSS.xlsx`.
- Template import berisi header:
  - `id_kelas_mapel`
  - `tahun_ajaran`
  - `hari`
  - `jam_mulai`
  - `jam_selesai`
  - `ruangan`
  - `status`

### 1.7 Hubungan dengan proses akademik lain
Jadwal pembelajaran dipakai langsung oleh modul absensi sesi. Di `SesiAbsensiController`, jadwal dipakai untuk:
- memvalidasi hari sesi
- memvalidasi rentang jam mulai dan jam selesai
- mengikat sesi ke petugas pengampu jadwal
- menjadi referensi saat rekap absensi santri dan petugas

Artinya, modul penjadwalan adalah fondasi untuk absensi KBM dan tidak berdiri sendiri.

### 1.8 Di mana flow modul penjadwalan bekerja
Flow modul penjadwalan berjalan di jalur ini:
- Route daftar CRUD, import, export, dan template: `backend/routes/api.php` pada prefix `api/akademik/jadwal-pembelajaran`
- Logika CRUD utama, validasi unik, normalisasi input, dan import CSV/XLS: `backend/app/Http/Controllers/Api/DataMaster/DataJadwalPembelajaranController.php`
- Struktur tabel dan relasi ke kelas mapel: `backend/app/Models/JadwalPembelajaran.php`
- Proses baca/tulis file Excel: `backend/app/Exports/DataJadwalPembelajaranExport.php` dan `backend/app/Imports/DataJadwalPembelajaranImport.php`
- Konsumsi jadwal oleh absensi sesi: `backend/app/Http/Controllers/Api/Akademik/SesiAbsensiController.php`

Urutan operasional yang terlihat di repo adalah:
1. Data jadwal dibuat atau diperbarui melalui controller master.
2. Data diekspor atau diimpor jika perlu sinkronisasi massal.
3. Jadwal dipakai oleh absensi sesi untuk validasi hari, jam, dan pengajar.
4. Jadwal kemudian menjadi referensi saat rekap kehadiran santri dan petugas.

### 1.9 Kebutuhan fungsional modul penjadwalan

Lihat detail kebutuhan fungsional di file [KebtuhanFungsional.md](KebtuhanFungsional.md#1-kebutuhan-fungsional-modul-penjadwalan) (8 kebutuhan: JP001-JP008).

## 2. Modul E-Rapor

### 2.1 Tujuan modul
Modul e-rapor mengelola konfigurasi penilaian, input nilai mapel, input nilai akhlak, komponen keseharian, catatan wali, generate rekap raport, ranking kelas, penerbitan raport, PDF raport, serta akses santri untuk melihat raport sendiri.

### 2.2 Komponen utama di repo
- Route utama: `backend/routes/api.php` pada prefix `api/akademik`
- Master konfigurasi: `BobotNilaiController`, `KkmMapelController`, `KonversiNilaiController`
- Input nilai: `NilaiMapelController`, `NilaiAkhlakController`
- Komponen raport: `RaportKeseharianController`, `RaportCatatanWaliController`
- Generasi raport: `RaportGenerateController`, `RangkingKelasController`
- PDF raport dan self-service: `RaportPdfController`
- Model inti: `DataRaport`, `DataNilaiSiswa`, `NilaiAkhlak`, `LogDownloadRaport`

### 2.3 Endpoint e-rapor yang tersedia
Semua endpoint akademik berada di middleware `auth:sanctum`.

#### Master konfigurasi
- `GET /api/akademik/bobot`
- `POST /api/akademik/bobot`
- `POST /api/akademik/bobot/set-default`
- `GET /api/akademik/bobot/{id}`
- `PUT /api/akademik/bobot/{id}`
- `DELETE /api/akademik/bobot/{id}`
- `GET /api/akademik/kkm-mapel`
- `POST /api/akademik/kkm-mapel`
- `GET /api/akademik/kkm-mapel/{id}`
- `PUT /api/akademik/kkm-mapel/{id}`
- `DELETE /api/akademik/kkm-mapel/{id}`
- `GET /api/akademik/konversi-nilai`
- `POST /api/akademik/konversi-nilai`
- `GET /api/akademik/konversi-nilai/{id}`
- `PUT /api/akademik/konversi-nilai/{id}`
- `DELETE /api/akademik/konversi-nilai/{id}`

#### Input nilai dan komponen raport
- `GET /api/akademik/nilai-mapel`
- `POST /api/akademik/nilai-mapel`
- `GET /api/akademik/nilai-mapel/{kode_mapel}`
- `DELETE /api/akademik/nilai-mapel/{id}`
- `GET /api/akademik/nilai-akhlak`
- `GET /api/akademik/nilai-akhlak/bar`
- `POST /api/akademik/nilai-akhlak`
- `DELETE /api/akademik/nilai-akhlak/{id}`
- `GET /api/akademik/raport/keseharian`
- `POST /api/akademik/raport/keseharian`
- `GET /api/akademik/raport/catatan-wali`
- `POST /api/akademik/raport/catatan-wali`

#### Generate dan distribusi raport
- `GET /api/akademik/raport`
- `GET /api/akademik/raport/show`
- `POST /api/akademik/raport/generate`
- `POST /api/akademik/raport/rank`
- `POST /api/akademik/rangking-kelas/generate`
- `POST /api/akademik/raport/publish`
- `GET /api/akademik/raport/pdf`
- `GET /api/akademik/raport/self`
- `GET /api/akademik/raport/self/pdf`

### 2.4 Aturan nilai yang tercatat di repo
Dokumen `docs/henry/.ai/eraporflow.md` dan implementasi controller menunjukkan aturan berikut:
- Bobot nilai mapel bersifat global dan tetap: Tugas 20%, Ulangan 30%, Ujian Akhir 50%.
- KKM dipakai untuk cek status nilai akhir, bukan untuk menghitung nilai akhir.
- KKM antar jenjang diperlakukan sama.
- Nilai rapor mapel dibatasi maksimal 98 untuk tampilan rapor.
- Nilai di bawah 50 ditulis 50 dengan warna merah.
- Nilai 50 asli tetap 50 dengan warna hitam.
- Pembulatan memakai aturan half-up: desimal 1-4 turun, 5-9 naik.
- Rata-rata rapor dibulatkan 2 angka desimal.
- Nilai akhlak disimpan sebagai angka, lalu dipakai sebagai komponen raport.
- Keseharian anak memakai skala A/B/C/D untuk kebersihan, kerapian, dan keterampilan.

### 2.5 Data inti yang dipakai modul raport
#### `data_nilai_siswa`
Kolom penting yang dipakai controller antara lain:
- `nilai_akhir_mapel`
- `nilai_rapor_tampil`
- `flag_warna_rapor`
- `nilai_huruf`
- `predikat`

#### `data_raport`
Model `DataRaport` menyimpan data rekap utama raport dengan field penting:
- `nomor_induk`
- `kode_kelas`
- `tahun_ajaran`
- `semester`
- `jumlah_nilai`
- `rata_rata`
- `peringkat_kelas`
- `total_siswa_kelas`
- `hadir`
- `sakit`
- `izin`
- `alpha`
- `keseharian_kebersihan`
- `keseharian_kerapian`
- `keseharian_keterampilan`
- `status_raport`
- `catatan_wali`
- `id_wali_kelas`
- `tanggal_terbit`

### 2.6 Alur generate raport
Pada `RaportGenerateController`, alur utamanya adalah:
1. Ambil data santri berdasarkan `nomor_induk`.
2. Ambil nilai mapel dari `data_nilai_siswa` yang sudah memiliki `nilai_rapor_tampil`.
3. Hitung jumlah nilai dan rata-rata mapel.
4. Hitung ringkasan absensi dari relasi data absensi yang terhubung ke jadwal pembelajaran.
5. Ambil nilai akhlak per aspek.
6. Simpan atau update `data_raport` dengan status `DRAFT`.

Hal penting yang terlihat di implementasi:
- `generate()` hanya membuat rekap per santri dan semester.
- Data keseharian, catatan wali, dan `id_wali_kelas` dipertahankan dari data raport yang sudah ada jika tersedia.
- Status awal raport disimpan sebagai `DRAFT`.

### 2.7 Alur ranking kelas
Method `rank()` pada `RaportGenerateController` memakai data raport per kelas dan semester, lalu menghitung skor ranking berdasarkan kelompok mapel.

Rumus yang dipakai di kode adalah:
- `((nilai hifzh x 2) + (rata-rata diniyyah x 2) + (rata-rata umum x 1)) / 5`

Perilaku teknisnya:
- Mapel dikelompokkan ke `hifzh`, `diniyyah`, atau `umum` berdasarkan `kelompok_mapel` dan `nama_mapel`.
- Hasil ranking diurutkan dari skor tertinggi.
- Jika skor sama, pembanding berikutnya adalah nilai hifzh.
- `peringkat_kelas` dan `total_siswa_kelas` disimpan kembali ke `data_raport`.
- Logika tampilan top ranking mengikuti jumlah siswa: maksimal 10 untuk kelas besar, 5 untuk kelas kecil.

### 2.8 Publikasi raport
Method `publish()` mengubah data raport menjadi status `TERBIT` dan mengisi `tanggal_terbit`.
Fitur ini bisa dijalankan untuk seluruh kelas, satu semester, atau satu santri tertentu jika `nomor_induk` dikirim.

### 2.9 PDF raport dan self-service santri
`RaportPdfController` menyediakan dua jalur utama:
- Petugas mengunduh PDF raport lewat endpoint `GET /api/akademik/raport/pdf`
- Santri melihat atau mengunduh raport sendiri lewat `GET /api/akademik/raport/self` dan `GET /api/akademik/raport/self/pdf`

Aturan pentingnya:
- Santri hanya bisa mengakses data jika akun yang login memang akun santri.
- PDF dirender dengan template `pdf.raport`.
- Setiap unduhan dicatat ke tabel `log_download_raport` lewat model `LogDownloadRaport`.

### 2.10 Konversi nilai huruf dan predikat
Pada generate PDF dan detail raport, repo juga menyertakan konversi nilai ke huruf dan predikat.
Aturannya:
- Sistem membaca rule konversi dari `DataKonversiNilai`.
- Jika kelas punya `kode_unit`, sistem memprioritaskan konversi unit tersebut lalu fallback ke konversi global.
- Jika tidak ada `kode_unit`, sistem memakai konversi global.
- Output tambahan yang dihasilkan adalah `nilai_huruf` dan `predikat` per mapel.

### 2.11 Keterkaitan dengan absensi
Ringkasan raport juga mengambil data absensi dari rangkaian tabel:
- `absensi_santri`
- `sesi_absensi`
- `jadwal_pembelajaran`
- `data_kelas_mapel`

Hasil ringkasannya adalah:
- hadir
- sakit
- izin
- alpha

Ini menunjukkan bahwa raport di repo ini bukan hanya kumpulan nilai, tetapi juga gabungan nilai akademik, absensi, akhlak, dan catatan wali.

### 2.12 Di mana flow modul e-rapor bekerja
Flow e-rapor berjalan di jalur file berikut:
- Route akademik raport, nilai, bobot, KKM, dan konversi: `backend/routes/api.php` pada prefix `api/akademik`
- Input dan perhitungan nilai mapel: `backend/app/Http/Controllers/Api/Akademik/NilaiMapelController.php`
- Input nilai akhlak: `backend/app/Http/Controllers/Api/Akademik/NilaiAkhlakController.php`
- Komponen keseharian raport: `backend/app/Http/Controllers/Api/Akademik/RaportKeseharianController.php`
- Catatan wali kelas: `backend/app/Http/Controllers/Api/Akademik/RaportCatatanWaliController.php`
- Generate rekap raport dan ranking: `backend/app/Http/Controllers/Api/Akademik/RaportGenerateController.php`
- Generate ranking kelas alternatif: `backend/app/Http/Controllers/Api/Akademik/RangkingKelasController.php`
- PDF raport dan self-service santri: `backend/app/Http/Controllers/Api/Akademik/RaportPdfController.php`
- Penyimpanan status dan data rekap utama: `backend/app/Models/DataRaport.php`
- Penyimpanan detail nilai mapel: `backend/app/Models/DataNilaiSiswa.php`
- Penyimpanan log unduhan: `backend/app/Models/LogDownloadRaport.php`

Urutan flow e-rapor yang terjadi di repo adalah:
1. Admin menetapkan bobot, KKM, dan konversi nilai.
2. Pengajar mengisi nilai mapel dan nilai akhlak.
3. Wali kelas mengisi keseharian dan catatan wali.
4. Sistem generate rekap raport per santri dan menghitung ranking kelas.
5. Status raport dipublikasikan dari `DRAFT` ke `TERBIT`.
6. Petugas atau santri mengunduh PDF raport, dan setiap unduhan dicatat.

### 2.13 Kebutuhan fungsional modul e-rapor

Lihat detail kebutuhan fungsional di file [KebtuhanFungsional.md](KebtuhanFungsional.md#2-kebutuhan-fungsional-modul-e-rapor) (25 kebutuhan: ER001-ER025) yang terbagi ke 4 kategori: konfigurasi (ER001-ER004), input nilai (ER005-ER012), komponen raport (ER013-ER016), dan generate/publikasi (ER017-ER025).

## 3. Dokumen pendukung yang relevan

Dokumen pendukung yang menjadi acuan isi rangkuman ini:
- `docs/henry/client-flow.md`
- `docs/henry/.ai/eraporflow.md`
- `docs/henry/.ai/backend-implementation-summary.md`
- `docs/henry/.ai/GRADING_SYSTEM_SPEC.md`
- `docs/henry/FE Guide/api-json-request-examples/raport-*.md`
- `docs/henry/FE Guide/api-json-request-examples/nilai-mapel-*.md`
- `docs/henry/FE Guide/api-json-request-examples/kkm-mapel-*.md`
- `docs/henry/FE Guide/api-json-request-examples/bobot-nilai-*.md`

## 4. Kesimpulan singkat

Repo ini sudah memiliki dua modul yang cukup matang untuk kebutuhan pelaporan magang:
- modul penjadwalan pembelajaran sebagai fondasi jadwal KBM dan absensi sesi
- modul e-rapor sebagai rantai lengkap dari konfigurasi bobot, input nilai, generate raport, ranking kelas, PDF, sampai akses santri sendiri

Seluruh ringkasan di atas ditulis dari isi repo ini, tanpa menambahkan asumsi di luar kode dan dokumen yang tersedia.
