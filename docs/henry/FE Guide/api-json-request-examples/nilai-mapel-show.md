# GET /api/akademik/nilai-mapel/{kode_mapel}

Auth: `sanctum`

Endpoint ini dipakai untuk detail nilai mapel per santri dan mapel.

## Contoh request

```json
{
  "path": {
    "kode_mapel": "MATH-01"
  },
  "query": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- `nomor_induk` wajib dikirim di query.
- `tahun_ajaran` dan `semester` opsional, tetapi sangat disarankan supaya data yang diambil spesifik.
