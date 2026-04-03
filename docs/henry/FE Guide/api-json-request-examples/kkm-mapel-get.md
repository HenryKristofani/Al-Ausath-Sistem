# GET /api/akademik/kkm-mapel

Auth: `sanctum`

Endpoint ini dipakai untuk list KKM mapel.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kode_mapel": "MATH-01",
    "kode_unit": "U01",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- `kode_unit` mendukung fallback data global saat unit spesifik tidak ada.
- `per_page` opsional.
