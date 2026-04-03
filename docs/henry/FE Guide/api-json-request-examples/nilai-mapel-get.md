# GET /api/akademik/nilai-mapel

Auth: `sanctum`

Endpoint ini dipakai untuk list nilai mapel per santri.

## Contoh request

```json
{
  "query": {
    "nomor_induk": "2025001",
    "kode_mapel": "MATH-01",
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "per_page": 10
  }
}
```

## Catatan

- `nomor_induk` wajib diisi.
- Query lain opsional untuk filter list.
