# GET /api/akademik/nilai-akhlak

Auth: `sanctum`

Endpoint ini dipakai untuk list nilai akhlak per santri.

## Contoh request

```json
{
  "query": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "aspek": "AKHLAK",
    "per_page": 10
  }
}
```

## Catatan

- `nomor_induk` wajib diisi.
- `tahun_ajaran`, `semester`, dan `aspek` opsional untuk filter tambahan.
