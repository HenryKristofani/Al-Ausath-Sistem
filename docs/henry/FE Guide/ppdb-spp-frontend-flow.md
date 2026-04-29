# FE Guide: PPDB + SPP Flow (Tagihan dan Pembayaran)

## 1. Tujuan
Dokumen ini menjadi panduan implementasi frontend untuk alur:
- Upload berkas PPDB
- Verifikasi penerimaan santri
- Toggle ON/OFF pertanyaan tes PPDB
- SPP dengan 2 fitur utama: Tagihan dan Pembayaran

## 2. Struktur Menu Frontend

### 2.1 PPDB
- Pendaftar
- Upload Berkas
- Verifikasi Pendaftar
- Konfigurasi Tes (Toggle pertanyaan ON/OFF)

### 2.2 SPP
- Tagihan
- Pembayaran
  - Proses Pembayaran
  - Verifikasi Pembayaran

## 3. API Endpoint yang Dipakai

### 3.1 PPDB
- GET `/api/administrasi/ppdb/pendaftar`
- GET `/api/administrasi/ppdb/pendaftar/{id}`
- PUT `/api/administrasi/ppdb/pendaftar/{id}`
- POST `/api/administrasi/ppdb/pendaftar/{id}/berkas`
- PUT `/api/administrasi/ppdb/pendaftar/{id}/verifikasi`
- PUT `/api/administrasi/ppdb/tes/konfigurasi/{jenjang}`

### 3.2 SPP
- GET `/api/administrasi/pembayaran/tagihan`
- GET `/api/administrasi/pembayaran/proses`
- GET `/api/administrasi/pembayaran/verifikasi`
- GET `/api/administrasi/pembayaran/{id}/detail`
- PUT `/api/administrasi/pembayaran/{id}/status`
- DELETE `/api/administrasi/pembayaran/{id}`
- GET `/api/administrasi/pembayaran/options`
- GET `/api/administrasi/pembayaran/ringkasan`

## 4. Halaman PPDB

### 4.1 Upload Berkas
Form minimal:
- jenis_berkas
- file (multipart) atau file_path (string)

Jenis berkas yang disarankan:
- akta
- kk
- surat_rekomendasi
- surat_pernyataan

Catatan implementasi:
- Gunakan multipart/form-data jika kirim file.
- Setelah sukses upload, lakukan refresh detail pendaftar (`GET /pendaftar/{id}`) agar data berkas langsung tampil di POV admin.

### 4.2 Verifikasi Penerimaan Santri
Request verifikasi dapat memakai salah satu field status berikut:
- hasil
- status_verifikasi
- status

Nilai status yang direkomendasikan:
- pending
- diterima
- ditolak

Jika diterima dan ingin langsung integrasi ke data santri:
- set `integrasikan_langsung_ke_santri = true`
- kirim `kode_kelas_diterima`

### 4.3 Toggle Pertanyaan Tes (ON/OFF)
Endpoint:
- PUT `/api/administrasi/ppdb/tes/konfigurasi/{jenjang}`

Payload yang aman:
- fitur_soal_aktif: boolean (true/false)
- soal_tes: string (opsional)
- form_schema: array (opsional)

Frontend boleh kirim varian nilai toggle:
- on/off
- true/false
- 1/0

Server akan normalisasi dan simpan persist ke database.

## 5. Halaman SPP: Tagihan

### 5.1 Tujuan
Menampilkan daftar ringkas tagihan semua entitas (santri master data dan calon santri PPDB).

### 5.2 Kolom Tabel
- Nama Unit
- Nomor Induk
- Nama Lengkap
- Kelas Sekarang
- Tahun Ajaran
- Status
- Total Tagihan
- Total Dibayar
- Total Tunggakan

### 5.3 Sumber Data
- Master data santri
- Data PPDB (termasuk biaya administrasi)

### 5.4 Integrasi PPDB
- Calon santri PPDB tetap masuk daftar tagihan.
- Jika biaya administrasi belum terverifikasi, status penerimaan belum bisa final.

## 6. Halaman SPP: Pembayaran

### 6.1 Submenu
- Proses Pembayaran
- Verifikasi Pembayaran

### 6.2 Proses Pembayaran
Tujuan:
- Filter siswa berdasarkan kelas/unit
- Menampilkan daftar invoice/tagihan per siswa
- Aksi cepat ke halaman detail

Filter yang disarankan:
- Kode Unit
- Kode Kelas
- Pencarian nama/nomor induk

Data yang ditampilkan per santri:
- Nama Lengkap
- Jenis Kelamin
- Nomor Induk
- Unit Sekarang
- Kelas Sekarang
- Status
- Daftar invoice

### 6.3 Verifikasi Pembayaran
Kolom tabel:
- Nama Unit
- Nomor Induk
- Nama Lengkap
- Nomor Invoice
- Total Pembayaran
- Jenis Transaksi
- Status Pembayaran
- Waktu Invoice
- Aksi

Aksi:
- Detail
- Status
- Hapus

Status pembayaran untuk kontrol frontend:
- menunggu_pembayaran
- menunggu_konfirmasi
- dibatalkan

Status tambahan dari sistem (read-only di badge):
- lunas

## 7. Halaman Detail Pembayaran (Halaman Khusus)

Endpoint:
- GET `/api/administrasi/pembayaran/{id}/detail`

Konten wajib:
- Profil santri/pendaftar
- Informasi invoice
- Riwayat pembayaran
- Tagihan kustom (jika ada)
- Informasi kwitansi (jika tersedia)

## 8. Alur End-to-End yang Direkomendasikan

1. Calon santri daftar PPDB.
2. Admin verifikasi biodata dan tes.
3. Sistem membuat tagihan administrasi PPDB.
4. Pembayaran dilakukan via WhatsApp (off-platform).
5. Admin verifikasi pembayaran pada halaman Verifikasi Pembayaran.
6. Jika valid maka status menjadi lunas, kwitansi tersedia otomatis.
7. Jika pendaftar berstatus diterima dan pembayaran valid, data santri dan akun santri diintegrasikan ke master data.
8. Nomor induk tergenerate setelah daftar ulang/pembayaran, dengan format: jenjang/tahun_masuk/no_absen.

## 9. Catatan UI/UX
- Pisahkan visual tab Tagihan dan Pembayaran dengan heading tegas.
- Tampilkan badge status dengan warna konsisten:
  - menunggu_pembayaran: merah
  - menunggu_konfirmasi: biru/oranye
  - dibatalkan: abu/merah gelap
  - lunas: hijau
- Pada Verifikasi Pembayaran, aksi utama adalah Detail dan Ubah Status.
- Gunakan drawer/modal untuk ubah status agar cepat tanpa pindah halaman.

## 10. Checklist Frontend
- [ ] Upload berkas mengirim multipart/form-data.
- [ ] Refresh data pendaftar setelah upload/verifikasi.
- [ ] Toggle tes memakai payload boolean stabil.
- [ ] Halaman Tagihan menampilkan kolom lengkap sesuai requirement.
- [ ] Halaman Proses Pembayaran mendukung filter kelas/unit.
- [ ] Halaman Verifikasi Pembayaran memiliki aksi detail/status/hapus.
- [ ] Halaman detail invoice menampilkan riwayat dan tagihan kustom.
- [ ] Status badge menggunakan mapping status terbaru.
