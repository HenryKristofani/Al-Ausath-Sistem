# POST /api/administrasi/mata-pelajaran/import

Auth: `sanctum`

Endpoint ini dipakai untuk import CSV (upsert berdasarkan `kode_mapel`).

## Contoh request (multipart/form-data)

```json
{
  "file": "data-mata-pelajaran.csv"
}
```

## Catatan

- Gunakan form-data dengan key `file`.
- Tipe file yang diterima: `csv` atau `txt`.
- Respons berisi ringkasan `inserted`, `updated`, `failed`, dan `error_rows`.
