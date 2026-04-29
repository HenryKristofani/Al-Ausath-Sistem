# GET /api/akademik/raport/self

Auth: `sanctum` dengan guard santri

Endpoint ini dipakai santri untuk melihat raport sendiri.

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

- `nomor_induk` tidak perlu dikirim karena diambil dari akun santri yang login.
- Response berisi raport, data santri, nilai mapel, dan nilai akhlak.
