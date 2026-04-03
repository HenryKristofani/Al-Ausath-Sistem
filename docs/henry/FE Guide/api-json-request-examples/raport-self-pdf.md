# GET /api/akademik/raport/self/pdf

Auth: `sanctum` dengan guard santri

Endpoint ini dipakai santri untuk mengunduh PDF raport sendiri.

## Contoh request

```json
{
  "query": {
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- Response berupa file PDF.
- `nomor_induk` diambil dari akun santri yang sedang login.
