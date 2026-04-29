# GET /api/akademik/raport/show

Auth: `sanctum`

Endpoint ini dipakai untuk detail satu raport lengkap.

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

- `nomor_induk`, `tahun_ajaran`, dan `semester` wajib diisi.
- Response berisi data raport, santri, nilai mapel, dan nilai akhlak.
