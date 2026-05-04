📌 Workflow Sistem PPDB & SPP (Current Issues & Expected Flow)
1. Belum bisa untuk mengintegrasikan Langsung ke data master karena apa ? belum bisa pilih kelas . kira kira kenapa ya ? apa karena tidak ada relasi antar tablenya atau gimana ya? tolong bantu saya untuk solving hal ini .
2. kondisi setelah kita menyimpan form di pov pendaftar yaitu di halaman http://localhost:3000/ppdb/dashboard/pengumuman harusnya kita juga menambahkan pembayaran yang berjumlah 100.000 
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

3. untuk halaman proses pembayaran mungkin untuk contoh fitur yang sudah existing bisa diterapkan kembali di website yang kita buat sekarang , untuk lengkapnya di foto. nah untuk detail tagihan bisa dilihat di foto yang kedua . nanti ketika pencet detail di generate new page saja , dengan id santri tersebut for example localhost / bill/detail/id 

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

🧩 Catatan implementasi backend saat ini:
- Otomatis integrasi PPDB → Master Data dibuat di `backend/app/Http/Controllers/Api/Administrasi/PembayaranSppController.php`.
- Proses integrasi berjalan ketika `PembayaranSpp.status` diubah menjadi `terverifikasi` dan `PpdbPendaftar.status_verifikasi` telah berstatus `diterima`, `lulus`, atau `accepted`.
- Syarat penting integrasi:
  - `kode_kelas_diterima` harus diisi dan valid.
  - `status_verifikasi` pendaftar harus `diterima` atau setara.
  - `PembayaranSpp` harus terkait `id_pendaftaran` pendaftar PPDB.
- Hasil integrasi:
  - membuat/memperbarui `DataSantri`
  - membuat/memperbarui `DataAkunSantri`
  - menyimpan `nomor_induk_generated` di `ppdb_pendaftar`
  - mengaitkan `id_santri` ke `PpdbPendaftar` dan `PembayaranSpp`

⚠️ Poin yang masih perlu diperhatikan:
- Backend sekarang membuat tagihan administrasi 100.000 otomatis saat pendaftar menyimpan form PPDB melalui `AuthController::updateFormPpdb`.
- Jika frontend tidak dapat memilih kelas, kemungkinan field `kode_kelas_diterima` belum dikirim pada verifikasi PPDB, karena model `PpdbPendaftar` saat ini hanya menyimpan kelas terima sebagai string bukan relasi Eloquent langsung.

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

