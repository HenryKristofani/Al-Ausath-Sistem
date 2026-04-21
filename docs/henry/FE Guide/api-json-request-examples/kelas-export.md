# GET /api/akademik/kelas/export

Auth: `sanctum`

Endpoint ini dipakai untuk export data kelas ke file Excel.

## Contoh request

```json
{
  "query": {
    "kode_unit": "MA",
    "tahun_ajaran": "2025/2026",
    "status": "AKTIF",
    "status_ppdb": "AKTIF",
    "q": "X-IPA"
  }
}
```

## Catatan

- Response berupa file `.xlsx` hasil `download`.
- Filter yang dipakai sama seperti endpoint list data kelas.
- Nama file export dibuat otomatis dengan timestamp.