# Sistem Informasi Akademik Pondok Pesantren Al-Ausath — Modul E-Rapor & Penjadwalan

## Konteks Sistem

Sistem ini adalah bagian dari **ERP Modul Pesantren** yang terintegrasi, mencakup:
- Modul A: Manajemen Kelas
- Modul B: Presensi
- Modul C: PPDB
- Modul D: Data Santri
- Modul E: Pembayaran SPP
- Modul F: **E-Rapor** ← (fokus dokumen ini)
- Modul G: **Penjadwalan** ← (fokus dokumen ini)
- Modul H: Manajemen Guru

Modul F (E-Rapor) dan G (Penjadwalan) adalah tanggung jawab **Henry Kristofani**.  
Modul ini terintegrasi dengan data santri (Modul D) dan data kehadiran/presensi (Modul B).

---

## Pengguna Sistem (Role)

| Role | Deskripsi |
|---|---|
| **Admin Akademik** | Mengelola data santri, guru, jadwal, dan dashboard |
| **Pengajar** | Input nilai, lihat statistik kelas |
| **Santri** | Lihat jadwal, nilai, dan rapor |
| **Wali Santri** | Lihat nilai anak, unduh rapor online |

---

## Sub-Modul G: Penjadwalan

### Fitur Utama

#### 1. Master Jadwal
- Membuat jadwal mingguan untuk semua kegiatan pesantren
- Jenis kegiatan yang dijadwalkan: **pembelajaran, kajian, tahfidz, ekstrakurikuler**
- Santri dapat melihat jadwal berdasarkan **tanggal** atau **jenis kegiatan**
- Detail jadwal mencakup: **waktu, lokasi, dan nama pengajar**

#### 2. Manajemen Perubahan Jadwal
- Admin dapat menambah, mengubah, dan menghapus jadwal
- Sistem **memvalidasi bentrok jadwal** secara otomatis
- Setiap perubahan jadwal memicu **notifikasi otomatis** ke santri/wali santri
- Sistem menyimpan **log riwayat perubahan jadwal**

#### 3. Export Jadwal
- Jadwal dapat diunduh dalam format **PDF**

---

## Sub-Modul F: Penilaian & E-Rapor

### Fitur Utama

#### 1. Komponen & Bobot Penilaian
- Admin/pengajar dapat mengatur **bobot nilai** per komponen
- Contoh bobot: UTS 30%, UAS 40%, Tugas 20%, Kehadiran 10%
- Terdapat pengaturan **KKM (Kriteria Ketuntasan Minimal)** per mata pelajaran

#### 2. Input Nilai
- Pengajar memilih kelas yang diampu, lalu menginput nilai per santri per mata pelajaran
- Komponen yang dapat diinput: **nilai harian, UTS, UAS**
- Pengajar dapat mengubah nilai yang sudah diinput
- Input bisa dilakukan **dari mana saja** (berbasis web)

#### 3. Kalkulasi Otomatis
- Sistem menghitung **nilai akhir secara otomatis** berdasarkan bobot yang telah dikonfigurasi
- Tidak perlu kalkulasi manual

#### 4. Generate E-Rapor
- Sistem menghasilkan **rapor digital** secara otomatis
- Rapor memuat **grafik perkembangan nilai** per semester
- Rapor dapat **diunduh dalam format PDF** untuk dicetak
- Wali santri dapat mengakses rapor **secara online tanpa datang ke pesantren**

#### 5. Raport Akhlak
- Selain nilai akademik, terdapat penilaian **sikap dan perilaku santri**
- Merupakan fitur khusus pesantren yang tidak dimiliki sistem sejenis (Google Classroom, Siskesakti)

---

## Dashboard & Analisis

### Dashboard Admin / Bagian Akademik
- Overview **nilai rata-rata per kelas**
- Statistik dan grafik perkembangan akademik santri
- Identifikasi santri **berprestasi** atau yang **perlu bimbingan**
- Data untuk evaluasi kurikulum

### Dashboard Pengajar
- Input nilai untuk kelas yang diampu
- Melihat **statistik kelas** yang diajar

### Dashboard Santri
- Melihat nilai per mata pelajaran
- Melihat dan mengunduh rapor
- Tracking perkembangan akademik sendiri per semester

---

## Alur Proses Bisnis (Ringkasan)

### Alur Input Nilai & Generate Rapor
```
Pengajar Login → Pilih Kelas → Input Nilai (Harian/UTS/UAS)
→ Simpan Data → Sistem Kalkulasi Otomatis → Update Statistik
→ [Santri/Wali request download] → Generate PDF E-Rapor → File siap unduh
→ Santri/Wali: Lihat Grafik → Unduh/Cetak Rapor
```

### Alur Pengelolaan Jadwal
```
Admin Login → Pilih Menu Jadwal → Tambah/Edit/Hapus Jadwal
→ Sistem Validasi Bentrok → Simpan ke Database
→ Catat Log History → Kirim Notifikasi → Santri/Wali terima notifikasi & lihat jadwal baru
```

---

## Requirement Functional (RF) Lengkap

| RF-ID | Deskripsi |
|---|---|
| RF-01 | Admin dan pengajar dapat login ke sistem |
| RF-02 | Sistem membedakan hak akses berdasarkan role |
| RF-03 | Semua pengguna dapat melihat jadwal kegiatan |
| RF-04 | Santri dapat mengakses informasi akademik secara online |
| RF-04.1 | Santri dapat melihat jadwal berdasarkan tanggal |
| RF-04.2 | Santri dapat melihat jadwal berdasarkan jenis kegiatan (belajar, tahfidz) |
| RF-04.3 | Santri dapat melihat detail jadwal (waktu, lokasi, pengajar) |
| RF-05 | Pengajar dapat mengelola nilai akademik santri |
| RF-05.1 | Pengajar dapat melihat daftar santri yang diampu |
| RF-05.2 | Pengajar dapat menginput nilai santri |
| RF-05.3 | Pengajar dapat mengubah nilai santri |
| RF-05.4 | Sistem menghitung nilai akhir otomatis berdasarkan bobot |
| RF-06 | Admin dapat mengelola data santri (tambah, ubah, hapus) |
| RF-07 | Admin dapat mengelola data pengajar (tambah, ubah, hapus) |
| RF-08 | Admin dapat mengelola jadwal kegiatan |
| RF-08.1 | Admin dapat menambahkan jadwal |
| RF-08.2 | Admin dapat mengubah jadwal |
| RF-08.3 | Admin dapat menghapus jadwal |
| RF-08.4 | Sistem menampilkan notifikasi perubahan jadwal |
| RF-09 | Sistem menyimpan riwayat perubahan jadwal dan nilai akademik |
| RF-10 | Admin dapat melihat dashboard akademik |
| RF-10.1 | Dashboard menampilkan statistik nilai santri |
| RF-10.2 | Dashboard menampilkan grafik perkembangan akademik |
| RF-11 | Santri dapat melihat dan mengunduh e-rapor |
| RF-12 | Admin dan pengajar dapat logout |

---

## Tech Stack

| Komponen | Teknologi |
|---|---|
| Backend | Laravel (PHP, MVC pattern) |
| Frontend | TypeScript + React |
| Database | PostgreSQL |
| Server Lokal (dev) | Laragon |
| Editor | Visual Studio Code |

---

## Keunggulan vs Sistem Sejenis

| Fitur | Sistem Ini | Siskesakti | Google Classroom |
|---|---|---|---|
| Jadwal kegiatan pesantren lengkap | ✅ | ❌ | ❌ |
| Validasi bentrok jadwal | ✅ | ❌ | ❌ |
| Penilaian multikomponen | ✅ | ✅ | ✅ |
| KKM per mata pelajaran | ✅ | ❌ | ❌ |
| Generate e-rapor PDF | ✅ | ✅ | ❌ |
| Penilaian akhlak santri | ✅ | ❌ | ❌ |
| Akses rapor online wali santri | ✅ | ✅ | ❌ |
| Kepemilikan data penuh | ✅ | ❌ | ❌ |
| Biaya | Gratis | Berlangganan | Gratis |

---

## Catatan Penting untuk AI

- Sistem ini khusus untuk konteks **pondok pesantren** dengan 425 santri
- Istilah **"santri"** = siswa/peserta didik di pesantren
- Istilah **"wali santri"** = orang tua/wali dari santri
- Istilah **"pengajar"** = guru/ustadz yang mengajar di pesantren
- **E-Rapor** adalah versi digital dari rapor tradisional yang bisa diakses online
- Modul ini **terintegrasi** — data nilai dapat dipengaruhi oleh data kehadiran dari Modul Presensi
- Semua perhitungan nilai dilakukan **otomatis oleh sistem** sesuai bobot yang dikonfigurasi admin
