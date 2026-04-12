# GET /api/administrasi/mata-pelajaran/import-template

Auth: `sanctum`

Endpoint ini dipakai untuk mengunduh template CSV import data mata pelajaran.

## Contoh request

```json
{}
```

## Catatan

- Respons berupa file CSV dengan header:
  - `kode_mapel`
  - `nama_mapel`
  - `kode_unit`
  - `kelompok_mapel`
  - `urutan`
  - `keterangan`
  - `status`
