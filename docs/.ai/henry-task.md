# Henry Kristofani — Task Plan: Modul E-Rapor

> Referensi dokumen: `convert-pdf-sistem-akademik-erapor-henry.md`
> Status DB audit: fondasi tersedia, beberapa tabel masih gap (bobot, KKM, akhlak)
> **Scope:** Backend API only — repo ini hanya menyediakan JSON API. Frontend ada di repo terpisah.

---

## Fase 1 — Database: Lengkapi Tabel yang Masih Gap

### 1.1 Migration: Konfigurasi Bobot Nilai

- [ ] Buat migration `create_bobot_nilai_table`
- [ ] Kolom: `id_bobot`, `kode_mapel`, `kode_unit`, `tahun_ajaran`, `semester`, `bobot_harian` (%), `bobot_uts` (%), `bobot_uas` (%), `bobot_kehadiran` (%), `created_at`, `updated_at`
- [ ] Constraint: unique(`kode_mapel`, `kode_unit`, `tahun_ajaran`, `semester`)
- [ ] FK: ke `data_mata_pelajaran` dan `data_unit`

### 1.2 Migration: KKM per Mata Pelajaran

- [ ] Buat migration `create_kkm_mapel_table`
- [ ] Kolom: `id_kkm`, `kode_mapel`, `kode_unit`, `tahun_ajaran`, `semester`, `nilai_kkm` (decimal), `keterangan`, `created_at`
- [ ] Constraint: unique(`kode_mapel`, `kode_unit`, `tahun_ajaran`, `semester`)
- [ ] FK: ke `data_mata_pelajaran` dan `data_unit`

### 1.3 Migration: Nilai Akhlak Santri

- [ ] Buat migration `create_nilai_akhlak_table`
- [ ] Kolom: `id_akhlak`, `nomor_induk`, `tahun_ajaran`, `semester`, `aspek` (string, e.g. kedisiplinan/ibadah/akhlak), `predikat` (A/B/C/D), `deskripsi`, `id_petugas_input`, `created_at`, `updated_at`
- [ ] Constraint: unique(`nomor_induk`, `tahun_ajaran`, `semester`, `aspek`)
- [ ] FK: ke `data_santri` dan `data_petugas`

### 1.4 Model Eloquent Baru

- [ ] Buat `app/Models/BobotNilai.php` (fillable, relasi ke DataMataPelajaran, DataUnit)
- [ ] Buat `app/Models/KkmMapel.php` (fillable, relasi ke DataMataPelajaran, DataUnit)
- [ ] Buat `app/Models/NilaiAkhlak.php` (fillable, relasi ke DataSantri, DataPetugas)

---

## Fase 2 — Backend API: Master Konfigurasi

> Prefix route: `api/akademik/`

### 2.1 Bobot Nilai

- [ ] `BobotNilaiController` (CRUD) di `app/Http/Controllers/Api/Akademik/`
  - `GET  /bobot-nilai` — list (filter: kode_mapel, kode_unit, tahun_ajaran, semester)
  - `POST /bobot-nilai` — create (validasi total bobot = 100%)
  - `GET  /bobot-nilai/{id}` — show
  - `PUT  /bobot-nilai/{id}` — update (validasi total bobot = 100%)
  - `DELETE /bobot-nilai/{id}` — destroy
- [ ] Validasi: jumlah semua bobot harus == 100

### 2.2 KKM

- [ ] `KkmMapelController` (CRUD) di `app/Http/Controllers/Api/Akademik/`
  - `GET  /kkm` — list (filter: kode_mapel, kode_unit, semester)
  - `POST /kkm` — create
  - `GET  /kkm/{id}` — show
  - `PUT  /kkm/{id}` — update
  - `DELETE /kkm/{id}` — destroy

### 2.3 Konversi Nilai (tabel sudah ada, API belum)

- [ ] `KonversiNilaiController` (CRUD) di `app/Http/Controllers/Api/Akademik/`
  - `GET  /konversi-nilai` — list per kode_unit
  - `POST /konversi-nilai` — create
  - `GET  /konversi-nilai/{id}` — show
  - `PUT  /konversi-nilai/{id}` — update
  - `DELETE /konversi-nilai/{id}` — destroy

### 2.4 Daftarkan Routes

- [ ] Tambah prefix group `Route::prefix('akademik')` di `routes/api.php`
- [ ] Bungkus semua route akademik dalam `auth:sanctum` middleware

---

## Fase 3 — Backend API: Input Nilai

### 3.1 Data Nilai Siswa

- [ ] `NilaiSiswaController` di `app/Http/Controllers/Api/Akademik/`
  - `GET  /nilai` — list (filter: kode_kelas, kode_mapel, tahun_ajaran, semester)
  - `POST /nilai` — create (input nilai harian/UTS/UAS)
  - `GET  /nilai/{id}` — show
  - `PUT  /nilai/{id}` — update nilai
  - `DELETE /nilai/{id}` — hapus nilai

### 3.2 Kalkulasi Nilai Akhir Otomatis

- [ ] Buat `app/Services/KalkulasiNilaiService.php`
  - Method `hitungNilaiAkhir(nomor_induk, kode_mapel, tahun_ajaran, semester)`:
    - Ambil bobot dari `bobot_nilai`
    - Hitung: `(nilai_harian × bobot_harian) + (nilai_uts × bobot_uts) + (nilai_uas × bobot_uas)`
    - Tambahkan komponen kehadiran jika ada bobot_kehadiran
    - Kembalikan nilai akhir + status lulus/tidak (vs KKM)
- [ ] Panggil service ini setelah setiap create/update nilai

### 3.3 Nilai Akhlak

- [ ] `NilaiAkhlakController` (CRUD) di `app/Http/Controllers/Api/Akademik/`
  - `GET  /akhlak` — list per nomor_induk, semester
  - `POST /akhlak` — input penilaian akhlak
  - `PUT  /akhlak/{id}` — update
  - `DELETE /akhlak/{id}` — hapus

---

## Fase 4 — Backend API: Raport & PDF

### 4.1 Generate Raport

- [ ] `RaportController` di `app/Http/Controllers/Api/Akademik/`
  - `GET  /raport` — list raport (filter: kode_kelas, tahun_ajaran, semester)
  - `POST /raport/generate` — auto-generate raport satu kelas:
    - Agregasi semua nilai per santri
    - Hitung rata-rata, peringkat, total siswa
    - Rekap kehadiran dari `absensi_santri`
    - Insert/update `data_raport`
  - `GET  /raport/{id}` — detail raport satu santri (include nilai per mapel + akhlak)
  - `PUT  /raport/{id}/catatan` — update catatan wali kelas
  - `PUT  /raport/{id}/terbitkan` — ubah status DRAFT → TERBIT + set tanggal_terbit

### 4.2 Generate PDF E-Rapor

> **Keputusan Desain:**
>
> 1. Format rapor **sama untuk semua jenjang** — satu template universal
> 2. Yang berbeda hanya **jumlah mata pelajaran** (loop dinamis, tidak di-hardcode)
> 3. Sistem harus **fleksibel** — jenjang baru tidak perlu buat template baru
> 4. **Tidak ada batasan jumlah mapel** — template loop `@foreach` semua mapel dari `data_nilai_siswa`
> 5. **Sisakan 3–5 baris kosong** di akhir tabel nilai pada PDF untuk ekspansi mapel masa depan

- [ ] Install library PDF (DomPDF via `barryvdh/laravel-dompdf`)
- [ ] Buat **satu** Blade template server-side untuk PDF: `resources/views/pdf/raport.blade.php`
  - Ini **bukan view FE** — hanya dipakai DomPDF di sisi server untuk render PDF binary
  - Header: identitas santri, kelas, tahun ajaran, semester
  - Tabel nilai: `@foreach` mapel dari `data_nilai_siswa` (urut berdasarkan `urutan` di `data_mata_pelajaran`)
    - Kolom: No | Mata Pelajaran | Harian | UTS | UAS | Nilai Akhir | Huruf | KKM | Ket
    - **Setelah baris data, sisakan 3 baris kosong** untuk keperluan masa depan
  - Blok nilai akhlak (loop aspek dari `nilai_akhlak`)
  - Rekap kehadiran: Hadir / Sakit / Izin / Alpha
  - Peringkat kelas, catatan wali kelas, kolom tanda tangan
  - **Tidak ada struktural yang berbeda antar jenjang** — perbedaan hanya pada data yang diisi
- [ ] `GET /raport/{id}/pdf` — generate & return PDF sebagai binary stream (`Content-Type: application/pdf`)
- [ ] Catat log ke `log_download_raport` setiap kali endpoint PDF dipanggil

### 4.3 Akses Santri/Wali

- [ ] `GET /santri/raport` — santri melihat raport sendiri (guard santri)
- [ ] `GET /santri/raport/{id}/pdf` — santri download PDF raport sendiri
- [ ] Pastikan authorization: santri hanya bisa akses raport miliknya sendiri

---

## Dependency Antar Fase

```
Fase 1 (DB) → Fase 2 (Config API) → Fase 3 (Nilai API) → Fase 4 (Raport API)
```

Fase 2 dan Fase 3 bisa paralel setelah Fase 1 selesai.
Fase 4.1 (Generate Raport) dan Fase 4.2 (PDF) bisa dikerjakan paralel.

---

## Catatan Implementasi

### Keamanan & Akses

- Semua route akademik wajib di-protect `auth:sanctum`
- Pengajar hanya bisa input nilai untuk kelas mapel yang ada di `data_kelas_mapel` dengan `id_petugas`-nya
- Santri hanya bisa akses data dengan `nomor_induk` yang sesuai akunnya
- Gunakan `log_perubahan_absensi` (atau buat `log_perubahan_nilai` terpisah) untuk audit trail nilai yang diubah

### Performa

- Nilai akhir disimpan di kolom terpisah (cached), bukan dihitung on-the-fly, untuk performa

### Desain Rapor (Format Universal)

- **Satu template PDF untuk semua jenjang** — jangan buat template per jenjang
- Jumlah mapel **tidak di-hardcode** di template — selalu loop dinamis dari DB
- Urutan mapel di PDF ditentukan kolom `urutan` di tabel `data_mata_pelajaran`
- Template harus tetap rapi meskipun mapel berjumlah 5 atau 20+
- **Sisakan minimal 3 baris kosong** di akhir tabel nilai pada PDF (implementasi via `@for` counter)
- Jika mapel di masa depan bertambah, tidak perlu ubah template sama sekali
