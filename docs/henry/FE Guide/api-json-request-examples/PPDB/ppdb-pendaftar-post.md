# POST /api/administrasi/ppdb/pendaftar

Auth: `sanctum` (Role: Petugas Admin / Petugas PPDB)

Endpoint ini dipakai untuk membuat data pendaftar baru oleh admin.

## Contoh request body

```json
{
  "nama_calon": "Budi Santoso",
  "program_pendaftaran": "Reguler",
  "jenjang": "MTS",
  "jenis_kelamin": "L",
  "tanggal_daftar": "2026-04-22"
}
```

## Catatan

- Ini adalah rute untuk pembuatan manual oleh admin. Pendaftar biasanya mendaftar sendiri via rute public `/api/ppdb/pendaftaran/create-identitas`.
