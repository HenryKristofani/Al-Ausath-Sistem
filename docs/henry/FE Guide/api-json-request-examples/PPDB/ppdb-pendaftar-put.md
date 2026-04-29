# PUT /api/administrasi/ppdb/pendaftar/{id}

Auth: `sanctum` (Role: Petugas Admin / Petugas PPDB)

Endpoint ini dipakai untuk memperbarui data pendaftar oleh admin.

## Contoh request body

```json
{
  "nama_calon": "Budi Santoso Diperbarui",
  "program_pendaftaran": "Reguler",
  "jenjang": "MTS",
  "jenis_kelamin": "L"
}
```

## Catatan

- Field yang dikirim bisa disesuaikan dengan data yang ingin diupdate.
