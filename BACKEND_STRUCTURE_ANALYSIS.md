📌 Workflow Sistem PPDB & SPP (Current Issues & Expected Flow)
🚨 1. Masalah yang Ditemukan (Current Issues)
1.1 Upload File
Data upload file:
❌ Belum tersimpan di database
❌ Tidak muncul di POV Admin
1.2 Status Penerimaan Santri
❌ Tidak bisa mengubah status penerimaan santri
Expected:
Admin bisa update status:
Menunggu
Diterima
Ditolak
1.3 Toggle Pertanyaan (On/Off)
❌ Status ON/OFF pertanyaan tidak tersimpan
Case:
Tes aktif → ingin dimatikan → gagal
1.4 SPP (UI & Flow)
❌ UI membingungkan
❌ Flow belum jelas
❌ Belum dipisah antara:
Tagihan
Pembayaran
🧩 2. Struktur Fitur SPP (Expected Design)
📊 2.1 Fitur: TAGIHAN
📌 Deskripsi

Menampilkan daftar tagihan seluruh santri (termasuk calon santri PPDB)

📋 Field Data
Nama Unit
Nomor Induk
Nama Lengkap
Kelas Sekarang
Tahun Ajaran
Status
Total Tagihan
Total Dibayar
Total Tunggakan
🔗 Relasi Data
Data diambil dari:
Master Data Santri
Data PPDB
💰 Kasus PPDB
Biaya administrasi: Rp 100.000
Flow:
Calon santri daftar
Masuk ke tagihan
Jika belum bayar:
❌ Tidak bisa diterima
Jika sudah bayar:
✅ Bisa diverifikasi admin
✅ Bisa lanjut ke penerimaan
💳 2.2 Fitur: PEMBAYARAN

Terdapat 2 Sub Menu:

Proses Pembayaran
Verifikasi Pembayaran
🔄 A. Proses Pembayaran
📌 Fungsi
Filtering data santri berdasarkan:
Kelas
Unit
Menampilkan data tagihan
⚙️ Fitur
Lihat tagihan
Verifikasi pembayaran
Check detail
🔍 Detail Pembayaran (Halaman Khusus)
📌 Berisi:
Data Santri
ID Invoice
Riwayat pembayaran
Tagihan custom (jika ada)
🔄 B. Verifikasi Pembayaran
📋 Field Data
Nama Unit
Nomor Induk
Nama Lengkap
Nomor Invoice
Total Pembayaran
Jenis Transaksi
Status Pembayaran
Waktu Invoice
Aksi
⚙️ Aksi
Detail
Ubah Status
Hapus
🔘 Status Pembayaran
Menunggu Pembayaran
Menunggu Konfirmasi
Dibatalkan
🔄 3. Flow PPDB ke Master Data
📌 Current Issue
Flow belum berjalan otomatis
✅ Expected Flow
Calon santri daftar (PPDB)
Masuk ke sistem
Bayar biaya administrasi
Admin verifikasi
Admin menerima santri
Sistem otomatis:
Membuat akun santri
Menyimpan ke master data
Generate Nomor Induk
🆔 Format Nomor Induk
Jenjang / Tahun Masuk / Nomor Absen
⚠️ Catatan
Nomor induk dibuat setelah daftar ulang / pembayaran selesai
💸 4. Alur SPP (Simplified Flow)
📌 Konsep Utama
Website:
→ Menampilkan tagihan
WhatsApp:
→ Digunakan untuk pembayaran
🔄 Flow
Admin membuat tagihan di sistem
Santri melihat tagihan di website
Santri melakukan pembayaran via WhatsApp
Admin melakukan verifikasi manual
Jika valid:
Status → Lunas
Sistem generate kwitansi otomatis
🧠 5. Catatan Integrasi Sistem

Mengacu pada prinsip sistem informasi berbasis web, penting bahwa:

Data harus terintegrasi antar modul
Tidak boleh ada data terpisah (silo)
Semua modul harus terhubung ke master data
🚀 6. Rekomendasi Perbaikan
✔ Perbaiki penyimpanan upload file (DB + admin view)
✔ Implement status management (santri & pembayaran)
✔ Fix toggle ON/OFF agar persist ke DB
✔ Pisahkan jelas:
Tagihan vs Pembayaran
✔ Pastikan relasi:
PPDB → Tagihan → Pembayaran → Master Data
✔ Auto-generate Nomor Induk setelah pembayaran

