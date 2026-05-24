# Task: In-App Notification Perubahan Jadwal

## Konteks Sistem

- Backend Laravel untuk Sistem Informasi Pesantren Al-Ausath
- User dengan role `santri` mencakup juga wali santri (satu akun yang sama)
- Notifikasi hanya dikirim ke santri yang **terpengaruh** oleh perubahan jadwal, bukan semua santri
- Notifikasi disimpan di database, lalu diambil frontend Next.js via polling
- Menggunakan **Laravel Database Notifications** (bawaan Laravel, tanpa package tambahan)

---

## Relasi untuk Menentukan Santri yang Terpengaruh

```
jadwal_pembelajaran → id_kelas_mapel
    → data_kelas_mapel (kode_kelas)
        → data_kelas (kode_kelas)
            → data_santri (kode_kelas)
```

Tidak ada foreign key langsung dari jadwal ke santri. Gunakan relasi berantai ini untuk mengambil santri yang masuk ke kelas yang jadwalnya berubah.

---

## Langkah Implementasi

### 1. Baca Struktur yang Sudah Ada
Sebelum mulai, baca terlebih dahulu file berikut agar implementasi sesuai dengan struktur yang sudah ada:
- `User.php`
- Controller jadwal yang ada (cari file controller terkait `jadwal_pembelajaran`)
- Migration/model dari tabel: `jadwal_pembelajaran`, `data_kelas_mapel`, `data_kelas`, `data_santri`

### 2. Buat Tabel Notifications
```bash
php artisan notifications:table
php artisan migrate
```

### 3. Buat Notification Class
Buat file `app/Notifications/JadwalBerubah.php` dengan:
- Channel: `database`
- Payload yang disimpan:
  - `pesan`
  - `jadwal_id`
  - `nama_mapel`
  - `waktu_baru`
  - `lokasi`

### 4. Kirim Notifikasi di Controller Jadwal
Di method `update` controller jadwal, setelah jadwal berhasil diupdate:
1. Ambil `kode_kelas` dari jadwal yang diubah lewat relasi `jadwal_pembelajaran → data_kelas_mapel`
2. Ambil semua `data_santri` yang memiliki `kode_kelas` tersebut
3. Join atau match ke tabel `users` untuk mendapatkan akun yang bisa menerima notifikasi
4. Kirim `Notification::send()` hanya ke user santri yang terdampak

### 5. Buat NotificationController
Buat `app/Http/Controllers/NotificationController.php` dengan 3 method:

| Method | Fungsi |
|--------|--------|
| `index` | Ambil 20 notifikasi terbaru milik user yang login beserta unread count |
| `markRead` | Tandai 1 notifikasi sebagai dibaca berdasarkan id |
| `readAll` | Tandai semua notifikasi milik user sebagai dibaca |

### 6. Daftarkan Route API
Tambahkan di `routes/api.php` dengan middleware `auth:sanctum`:

```
GET   /notifications
PATCH /notifications/{id}/read
POST  /notifications/read-all
```

---

## Catatan
Sesuaikan semua nama tabel, kolom, dan konvensi kode dengan yang sudah ada di project ini.
