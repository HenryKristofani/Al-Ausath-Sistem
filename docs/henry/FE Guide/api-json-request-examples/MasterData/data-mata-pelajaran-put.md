# PUT /api/administrasi/mata-pelajaran/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk memperbarui data mata pelajaran.

## Contoh request body

```json
{
  "kode_mapel": "MA-FIQ-01",
  "nama_mapel": "Fikih Lanjutan",
  "kode_unit": "MA",
  "kelompok_mapel": "WAJIB",
  "urutan": 2,
  "keterangan": "Update kurikulum",
  "status": "AKTIF"
}
```

## Catatan

- Semua field bersifat opsional pada update (`partial update`).
- Jika `kode_mapel` diubah, tetap harus unik.
- Backend menormalisasi `kode_mapel`, `kode_unit`, dan `status` ke huruf besar.
