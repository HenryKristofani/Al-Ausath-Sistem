# GET /api/akademik/raport/pdf

Auth: `sanctum`

Endpoint ini dipakai untuk mengunduh PDF raport oleh petugas.

## Contoh request

```json
{
  "query": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- Response berupa file PDF, bukan JSON.
- Data raport harus sudah ada terlebih dahulu.
