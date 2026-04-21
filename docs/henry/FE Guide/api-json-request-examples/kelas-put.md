# PUT /api/akademik/kelas/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk memperbarui data kelas.

## Contoh request body

```json
{
  "kode_unit": "MA",
  "kode_kelas": "X-IPA-1",
  "nama_kelas": "X IPA 1 A",
  "nama_jurusan": "IPA",
  "tahun_ajaran": "2025/2026",
  "status": "AKTIF",
  "status_ppdb": "NONAKTIF"
}
```

## Catatan

- Gunakan `id_kelas` sebagai path parameter `id`.
- Semua field bersifat `sometimes`, jadi FE bisa kirim field yang berubah saja.
- `kode_unit`, `kode_kelas`, `status`, dan `status_ppdb` akan dinormalisasi ke huruf besar.
- Jika `kode_kelas` diubah, backend tetap menjaga validasi unik untuk data aktif.