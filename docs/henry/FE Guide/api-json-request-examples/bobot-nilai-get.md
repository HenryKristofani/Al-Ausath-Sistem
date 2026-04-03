# GET /api/akademik/bobot

Auth: `sanctum`

Endpoint ini dipakai untuk list bobot nilai global.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- `per_page` opsional.
- `tahun_ajaran` dan `semester` dipakai untuk filter data.
