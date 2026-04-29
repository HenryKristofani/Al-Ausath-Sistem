# PUT /api/akademik/bobot/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk update bobot nilai.

## Contoh request body

```json
{
  "tahun_ajaran": "2025/2026",
  "semester": 2,
  "bobot_harian": 25,
  "bobot_uts": 25,
  "bobot_uas": 50
}
```

## Catatan

- Field boleh dikirim sebagian, tetapi total bobot tetap harus `100`.
- `semester` hanya `1` atau `2`.
