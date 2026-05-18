# PUT /api/akademik/nilai-mapel/{id} — Edit Nilai Mapel

Tujuan: Mengubah komponen nilai untuk satu mapel pada record `DataNilaiSiswa` yang sudah ada.

- Auth: `Authorization: Bearer <token>` (Sanctum)
- Method: `PUT`
- Path: `/api/akademik/nilai-mapel/{id}`

## Request JSON (contoh)

```json
{
  "nomor_induk": "SNTR12345",
  "kode_mapel": "MAT101",
  "kode_kelas": "KLSA",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "id_petugas_input": 12,
  "keterangan": "Revisi nilai",

  "tugas": [
    {"jenis": "PR", "nilai": 85},
    {"jenis": "PR", "nilai": 90},
    {"jenis": "MODUL_KOMPETENSI", "nilai": 88}
  ],

  "ulangan": [
    {"nilai": 80, "soal_disusun_pengajar": true, "diawasi_pengajar": true},
    {"nilai": 82, "soal_disusun_pengajar": true, "diawasi_pengajar": true},
    {"nilai": 84, "soal_disusun_pengajar": true, "diawasi_pengajar": true}
  ],

  "ujian_akhir": 87
}
```

Keterangan validasi penting (sesuai controller):
- `nomor_induk` harus ada di tabel `data_santri`
- `kode_mapel` harus ada di tabel `data_mata_pelajaran`
- `kode_kelas` harus cocok dengan kelas santri
- Minimal 3 elemen di `tugas` dan `ulangan`; hanya `ulangan` dengan `soal_disusun_pengajar=true` dan `diawasi_pengajar=true` dihitung
- `ujian_akhir` numeric 0-100

## Response (contoh sukses)

```json
{
  "message": "Nilai mapel berhasil diperbarui.",
  "data": {
    "id_nilai": 123,
    "nomor_induk": "SNTR12345",
    "kode_mapel": "MAT101",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "nilai_harian": 87.67,
    "nilai_uts": 82.00,
    "nilai_uas": 87.00,
    "nilai_akhir_mapel": 86.00,
    "nilai_rapor_tampil": 86,
    "flag_warna_rapor": "HITAM",
    "status_ketuntasan": "TUNTAS",
    "keterangan": "Revisi nilai",
    "id_petugas_input": 12
  }
}
```

## Notes
- Endpoint ini memeriksa apakah raport untuk `nomor_induk`/`tahun_ajaran`/`semester` sudah `TERBIT`; jika ya, pengubahan diblokir dan respon `403` dikembalikan.
- Scramble: project sudah menggunakan `dedoc/scramble` dan `AppServiceProvider` mengizinkan rute `api/*`, sehingga dokumentasi Scramble akan mencakup endpoint ini secara otomatis.

---

File contoh ini berfungsi sebagai referensi front-end untuk payload dan respons. Jika Anda mau, saya bisa juga menambahkan contoh Curl atau Postman koleksi.
