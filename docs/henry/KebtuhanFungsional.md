# Kebutuhan Fungsional Modul Penjadwalan dan E-Rapor

## 1. Kebutuhan Fungsional Modul Penjadwalan

| Kode | Kebutuhan Fungsional | Deskripsi | Aktor |
|------|----------------------|-----------|---------|
| FR001 | Membuat jadwal pembelajaran | Admin/petugas dapat membuat jadwal pembelajaran baru dengan mengisi kelas mapel, tahun ajaran, hari, jam mulai, jam selesai, ruangan, dan status. Sistem validasi memastikan kombinasi jadwal unik. | Admin, Petugas |
| FR002 | Melihat daftar jadwal pembelajaran | Admin/petugas dapat melihat daftar jadwal pembelajaran dengan filter berdasarkan kelas mapel, tahun ajaran, hari, status, atau keyword pencarian. | Admin, Petugas |
| FR003 | Melihat detail jadwal pembelajaran | Admin/petugas dapat melihat detail jadwal pembelajaran beserta data kelas, mata pelajaran, dan pengajar yang terkait. | Admin, Petugas |
| FR004 | Memperbarui jadwal pembelajaran | Admin/petugas dapat mengubah data jadwal pembelajaran yang sudah ada, dengan validasi kombinasi unik dan jam selesai > jam mulai. | Admin, Petugas |
| FR005 | Menghapus jadwal pembelajaran | Admin/petugas dapat menghapus jadwal pembelajaran. Sistem akan menolak penghapusan jika jadwal masih dipakai oleh data terkait (absensi sesi). | Admin, Petugas |
| FR006 | Mengimpor jadwal pembelajaran | Admin/petugas dapat mengimpor data jadwal dari file CSV, XLSX, atau XLS. Impor bersifat upsert berbasis kombinasi unik. | Admin |
| FR007 | Mengekspor jadwal pembelajaran | Admin/petugas dapat mengekspor data jadwal pembelajaran ke file Excel sesuai filter yang diterapkan. | Admin, Petugas |
| FR008 | Mengunduh template impor jadwal | Admin/petugas dapat mengunduh template CSV untuk impor jadwal pembelajaran dengan header yang sesuai. | Admin, Petugas |

## 2. Kebutuhan Fungsional Modul E-Rapor

### 2.1 Konfigurasi dan Referensi Nilai

| Kode | Kebutuhan Fungsional | Deskripsi | Aktor |
|------|----------------------|-----------|----------|
| FR009 | Mengelola bobot nilai | Admin dapat membuat, melihat, memperbarui, dan menghapus bobot nilai global yang diterapkan ke semua mapel. Bobot terdiri dari tugas (20%), ulangan (30%), ujian akhir (50%). Total harus 100. | Admin |
| FR010 | Menetapkan bobot default | Admin dapat menetapkan bobot default yang berlaku untuk semua mapel jika tidak ada konfigurasi khusus. | Admin |
| FR011 | Mengelola KKM mapel | Admin/petugas dapat membuat, melihat, memperbarui, dan menghapus KKM (Kriteria Ketuntasan Minimal) per mapel. KKM antar jenjang diperlakukan sama. | Admin, Petugas |
| FR012 | Mengelola konversi nilai | Admin dapat membuat, melihat, memperbarui, dan menghapus rule konversi nilai ke huruf dan predikat. Konversi dapat ditetapkan per unit atau global. | Admin |

### 2.2 Input Nilai

| Kode | Kebutuhan Fungsional | Deskripsi | Aktor |
|------|----------------------|-----------|---------|
| FR013 | Mengisi nilai mapel | Pengajar dapat mengisi nilai komponen mapel (nilai harian, UTS, UAS, atau nilai akhir) per santri per kelas per semester. Sistem otomatis menghitung nilai akhir dengan bobot dan normalisasi. | Pengajar |
| FR014 | Melihat daftar nilai mapel | Pengajar/admin dapat melihat daftar nilai mapel dengan filter berdasarkan nomor induk, kode mapel, tahun ajaran, semester, atau keyword. | Pengajar, Admin |
| FR015 | Melihat detail nilai mapel | Pengajar/admin dapat melihat detail nilai mapel seorang santri untuk mapel tertentu, termasuk nilai komponen dan nilai akhir yang telah dinormalisasi. | Pengajar, Admin |
| FR016 | Menghapus nilai mapel | Pengajar/admin dapat menghapus data nilai mapel seorang santri. | Pengajar, Admin |
| FR017 | Mengisi nilai akhlak | Pengajar/wali kelas dapat mengisi nilai akhlak per santri per aspek (angka dan deskripsi) per semester. | Pengajar, Wali Kelas |
| FR018 | Melihat daftar nilai akhlak | Admin/petugas dapat melihat daftar nilai akhlak dengan filter. | Admin, Petugas |
| FR019 | Melihat grafik nilai akhlak | Admin/petugas dapat melihat visualisasi grafik nilai akhlak per santri atau per kelas. | Admin, Petugas |
| FR020 | Menghapus nilai akhlak | Admin/petugas dapat menghapus data nilai akhlak. | Admin, Petugas |

### 2.3 Komponen Raport Pendukung

| Kode | Kebutuhan Fungsional | Deskripsi | Aktor |
|------|----------------------|-----------|---------|
| FR021 | Mengisi keseharian anak | Wali kelas dapat mengisi komponen keseharian anak (kebersihan, kerapian, keterampilan) dengan skala A/B/C/D per santri per semester. | Wali Kelas |
| FR022 | Melihat keseharian anak | Petugas/wali kelas dapat melihat data keseharian anak per santri per semester. | Petugas, Wali Kelas |
| FR023 | Mengisi catatan wali | Wali kelas dapat mengisi catatan perkembangan diri, pesan, dan menetapkan diri sebagai wali kelas santri. | Wali Kelas |
| FR024 | Melihat catatan wali | Admin/petugas dapat melihat catatan wali kelas per santri per semester. | Admin, Petugas |

### 2.4 Generate dan Publikasi Raport

| Kode | Kebutuhan Fungsional | Deskripsi | Aktor |
|------|----------------------|-----------|---------|
| FR025 | Membuat rekap raport | Admin/wali kelas dapat membuat rekap raport per santri per semester dengan mengumpulkan nilai mapel, nilai akhlak, absensi, dan komponen keseharian. Raport disimpan dengan status DRAFT. | Admin, Wali Kelas |
| FR026 | Melihat daftar raport | Admin/petugas dapat melihat daftar raport dengan filter berdasarkan status, nomor induk, kelas, tahun ajaran, semester, atau keyword. | Admin, Petugas |
| FR027 | Melihat detail raport | Admin/petugas dapat melihat detail rekap raport per santri lengkap dengan nilai mapel, nilai akhlak, absensi, dan komponen pendukung. | Admin, Petugas |
| FR028 | Menghitung ranking kelas | Admin dapat menghitung ranking per kelas per semester berdasarkan rumus: ((nilai hifzh x 2) + (rata-rata diniyyah x 2) + (rata-rata umum x 1)) / 5. Hasil disimpan ke raport. | Admin |
| FR029 | Menerbitkan raport | Admin dapat mengubah status raport dari DRAFT menjadi TERBIT untuk seluruh kelas, per semester, atau per santri. Tanggal terbit dicatat. | Admin |

### 2.5 Distribusi dan Akses Raport

| Kode | Kebutuhan Fungsional | Deskripsi | Aktor |
|------|----------------------|-----------|---------|
| FR030 | Mengunduh PDF raport (petugas) | Petugas dapat mengunduh PDF raport per santri. PDF berisi nilai mapel, nilai akhlak, absensi, keseharian, catatan wali, dan ranking. Unduhan dicatat ke log. | Petugas |
| FR031 | Melihat raport sendiri (santri) | Santri dapat melihat raport miliknya sendiri tanpa download untuk preview. | Santri |
| FR032 | Mengunduh PDF raport (santri) | Santri dapat mengunduh PDF raport miliknya sendiri. Setiap unduhan dicatat ke log. | Santri |
| FR033 | Mencatat log unduhan raport | Sistem otomatis mencatat setiap unduhan PDF raport dengan informasi: ID raport, nomor induk, tipe pengunduh, nama file, IP address, user agent, status, dan keterangan. | Sistem |
