# Henry Kristofani — Task Plan: Modul E-Rapor

> Referensi dokumen: `convert-pdf-sistem-akademik-erapor-henry.md`
> Status DB audit: fondasi tersedia, beberapa tabel masih gap (bobot, KKM, akhlak)
> **Scope:** Backend API only — repo ini hanya menyediakan JSON API. Frontend ada di repo terpisah.

---

## Fase 1 — Database: Lengkapi Tabel yang Masih Gap

### 1.1 Migration: `bobot_nilai`

> **Keputusan desain:** Tiap jenjang bisa punya bobot berbeda sesuai fitur setting di proposal.
> Bobot tidak terikat per mapel, tapi per **jenjang + tahun ajaran + semester**.
> Admin bisa set satu konfigurasi bobot yang berlaku untuk semua mapel pada jenjang tersebut.

- [ ] Buat migration `create_bobot_nilai_table`
- [ ] Kolom:
  - `id_bobot` — primary key
  - `jenjang` (varchar 20) — e.g. `MTS`, `MA`, `SD` — **bukan kode_unit, tapi jenjang eksplisit**
  - `kode_unit` (varchar 10, nullable) — FK ke `data_unit`, untuk scope per unit jika diperlukan
  - `tahun_ajaran` (varchar 20)
  - `semester` (smallint)
  - `bobot_harian` (decimal 5,2) — persen
  - `bobot_uts` (decimal 5,2) — persen
  - `bobot_uas` (decimal 5,2) — persen
  - `bobot_kehadiran` (decimal 5,2) — persen, default 0
  - `created_at`, `updated_at`
- [ ] Constraint: unique(`jenjang`, `kode_unit`, `tahun_ajaran`, `semester`)
- [ ] Validasi di application layer: `bobot_harian + bobot_uts + bobot_uas + bobot_kehadiran = 100`
- [ ] FK: `kode_unit` → `data_unit.kode_unit` (nullable, onDelete setNull)

### 1.2 Migration: `kkm_mapel`

> **Keputusan desain:** KKM bisa berbeda per **jenjang + mapel + tahun ajaran (angkatan)**.
> Contoh: Fiqih MTS Angkatan 2025 KKM-nya 70, Fiqih MA Angkatan 2026 KKM-nya 75.
> Gunakan `jenjang` + `kode_mapel` + `tahun_ajaran` + `semester` sebagai key unik.

- [ ] Buat migration `create_kkm_mapel_table`
- [ ] Kolom:
  - `id_kkm` — primary key
  - `kode_mapel` (varchar 20) — FK ke `data_mata_pelajaran`
  - `jenjang` (varchar 20) — e.g. `MTS`, `MA` — **bukan kode_unit**
  - `kode_unit` (varchar 10, nullable) — FK ke `data_unit`, opsional untuk scope lebih spesifik
  - `tahun_ajaran` (varchar 20) — merepresentasikan angkatan
  - `semester` (smallint)
  - `nilai_kkm` (decimal 5,2)
  - `keterangan` (text, nullable)
  - `created_at`
- [ ] Constraint: unique(`kode_mapel`, `jenjang`, `tahun_ajaran`, `semester`)
- [ ] FK: `kode_mapel` → `data_mata_pelajaran.kode_mapel`, `kode_unit` → `data_unit.kode_unit` (nullable)

### 1.3 Migration: `nilai_akhlak`

> **Keputusan desain:** Penilaian akhlak terdiri dari **banyak aspek** (kedisiplinan, ibadah, akhlak, kebersihan, dll).
> Tidak bisa disimpan dalam 1 kolom — gunakan model **1 baris per aspek per santri per semester**.
> Aspek-aspek bisa bertambah tanpa perlu alter table.

- [ ] Buat migration `create_nilai_akhlak_table`
- [ ] Kolom:
  - `id_akhlak` — primary key
  - `nomor_induk` (varchar 20) — FK ke `data_santri`
  - `tahun_ajaran` (varchar 20)
  - `semester` (smallint)
  - `aspek` (varchar 80) — nama aspek, e.g. `Kedisiplinan`, `Ibadah`, `Akhlak`, `Kebersihan`
  - `predikat` (varchar 5) — nilai huruf: `A`, `B`, `C`, `D`
  - `deskripsi` (text, nullable) — catatan narasi opsional per aspek
  - `id_petugas_input` (integer, nullable) — FK ke `data_petugas`
  - `created_at`, `updated_at`
- [ ] Constraint: unique(`nomor_induk`, `tahun_ajaran`, `semester`, `aspek`)
- [ ] FK: `nomor_induk` → `data_santri.nomor_induk` (onDelete cascade), `id_petugas_input` → `data_petugas.id_petugas` (onDelete setNull)
- [ ] **Tidak ada master tabel aspek** dulu — aspek diinput bebas sebagai string (bisa distandarisasi later via seeder/config)

### 1.4 Model Eloquent Baru

- [ ] Buat `app/Models/BobotNilai.php`
  - fillable: `jenjang`, `kode_unit`, `tahun_ajaran`, `semester`, `bobot_harian`, `bobot_uts`, `bobot_uas`, `bobot_kehadiran`
  - relasi: `unit()` belongsTo DataUnit
- [ ] Buat `app/Models/KkmMapel.php`
  - fillable: `kode_mapel`, `jenjang`, `kode_unit`, `tahun_ajaran`, `semester`, `nilai_kkm`, `keterangan`
  - relasi: `mataPelajaran()` belongsTo DataMataPelajaran, `unit()` belongsTo DataUnit
- [ ] Buat `app/Models/NilaiAkhlak.php`
  - fillable: `nomor_induk`, `tahun_ajaran`, `semester`, `aspek`, `predikat`, `deskripsi`, `id_petugas_input`
  - relasi: `santri()` belongsTo DataSantri, `petugas()` belongsTo DataPetugas
  - scope: `scopePerSantri($query, $nomor_induk, $tahun_ajaran, $semester)` untuk ambil semua aspek satu santri

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
      r

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
