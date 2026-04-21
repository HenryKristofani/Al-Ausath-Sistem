# GET /api/akademik/kelas/import-template

Auth: `sanctum`

Endpoint ini dipakai untuk mengunduh template CSV import data kelas.

## Contoh request

```json
{}
```

## Catatan

- Response berupa file CSV template.
- Header yang disediakan backend: `kode_unit`, `kode_kelas`, `nama_kelas`, `nama_jurusan`, `tahun_ajaran`, `status`, `status_ppdb`.