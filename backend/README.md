# ⚙️ Backend API - Sistem Informasi Akademik & Administrasi (SIAKAD e-Rapor)
### **Pondok Pesantren Al-Ausath Karanganyar**

![Laravel](https://img.shields.io/badge/Laravel-12.0-FF2D20?style=for-the-badge&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16-4169E1?style=for-the-badge&logo=postgresql)
![Sanctum](https://img.shields.io/badge/Authentication-Laravel_Sanctum-red?style=for-the-badge)

Repositori REST API Backend untuk Sistem Informasi Akademik, Presensi, e-Rapor Digital, Keuangan/SPP, dan PPDB Online Pondok Pesantren Al-Ausath Karanganyar.

---

## 🚀 Cakupan API & Layanan Backend

### 1. 🔐 Autentikasi & RBAC (Multi-Role)
- Multi-guard Authentication (Petugas Admin, Guru/Staf Pengajar, Santri/Wali Santri).
- Session & Token management via **Laravel Sanctum**.

### 2. 📚 Modul Data Master Akademik
- `/api/data-santri` – CRUD Data Santri, Status Aktif/Lulus, & Pembuatan Akun.
- `/api/petugas` – CRUD Data Guru & Staf Pengajar.
- `/api/kelas` – CRUD Data Kelas per Jenjang & Tahun Ajaran.
- `/api/unit` – CRUD Unit/Jenjang Pendidikan (PAUD, TK, SD, SMP, SMA).
- `/api/mapel` – CRUD Data Mata Pelajaran.
- `/api/kelas-mapel` – Pengalokasian Mapel & Pengajar di Kelas per Semester.
- `/api/jadwal-pembelajaran` – Pengaturan Jadwal Pembelajaran Santri.
- `/api/tahun-ajaran` – Manajemen Tahun Ajaran Aktif & Nonaktif.

### 3. 📋 Modul Presensi & Sesi Pembelajaran
- `/api/akademik/sesi-absensi/mulai` – Pembukaan Sesi Presensi oleh Pengajar.
- `/api/akademik/sesi-absensi/riwayat-santri` – Riwayat Presensi Santri dengan Filter `tahun_ajaran` & `semester`.
- `/api/akademik/sesi-absensi/rekap/santri` – Rekapitulasi Kehadiran Santri (Hadir, Sakit, Izin, Alfa).
- `/api/akademik/sesi-absensi/rekap/santri/mapel` – Rekap Kehadiran per Mata Pelajaran.

### 4. 📄 Modul Penilaian & e-Rapor Digital
- `/api/akademik/nilai-mapel` – Input & Upsert Nilai UTS, UAS, dan Harian Santri.
- `/api/akademik/nilai-akhlak` – Penilaian Akhlak & Catatan Keseharian.
- `/api/akademik/kkm` – Pengaturan Nilai KKM per Mapel/Unit.
- `/api/akademik/raport` – Inisiasi, Generate, Preview, dan Export Rapor Digital (PDF & Excel).

### 5. 💳 Modul Administrasi & SPP
- `/api/spp` – Setting Tarif SPP, Tagihan Rutin Santri, & Administrasi Bebas.
- `/api/pembayaran` – Pencatatan & Konfirmasi Pembayaran Santri.
- `/api/rekening` – Pengelolaan Rekening Bank Pesantren.

### 6. 📢 Modul PPDB & Pengumuman
- `/api/ppdb` – Pendaftaran Online, Cek Gelombang, & Kuota Pendaftar.
- `/api/pengumuman` – Publikasi Pengumuman Internal & Publik.

---

## 🛠️ Stack & Librari Backend

- **Framework**: Laravel 12.0
- **Bahasa**: PHP 8.2+
- **Database**: PostgreSQL (PgSQL)
- **PDF Generator**: `barryvdh/laravel-dompdf`
- **Excel Exporter**: `maatwebsite/excel`
- **API Documentation**: `dedoc/scramble`

---

## 💻 Panduan Instalasi & Pengoperasian

### 1. Prasyarat System
- PHP >= 8.2 (Extension: pdo_pgsql, OpenSSL, Mbstring, XML, Ctype, JSON, BCMath, GD)
- Composer >= 2.x
- Database PostgreSQL Server

### 2. Langkah Instalasi

```bash
# Clone repositori backend (jika terpisah)
git clone https://github.com/hafidnm/al-ausath-backend.git
cd backend

# Install dependensi PHP
composer install

# Salin file env & sesuaikan koneksi database
cp .env.example .env

# Generate Application Key
php artisan key:generate

# Konfigurasi Database PostgreSQL di .env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=siakad_alausath
DB_USERNAME=postgres
DB_PASSWORD=

# Migrasi Database & Seeder
php artisan migrate --seed

# Jalankan Server Development
php artisan serve
```

API Server akan berjalan di: `http://127.0.0.1:8000`.

---

## 📄 Dokumentasi API (Scramble)

Backend dilengkapi dengan dokumentasi API interaktif otomatis berbasis Scramble OpenAPI:
- **Swagger / UI Docs**: `http://127.0.0.1:8000/docs/api`

---

## 🔒 Lisensi

Hak Cipta &copy; 2026 **Pondok Pesantren Al-Ausath Karanganyar**. All rights reserved.
