# POST /api/akademik/bobot

Auth: `sanctum`

Endpoint ini dipakai untuk membuat bobot nilai global baru.

## Contoh request body

```json
{
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "bobot_harian": 20,
  "bobot_uts": 30,
  "bobot_uas": 50
}
```

## Catatan

- Total `bobot_harian + bobot_uts + bobot_uas` harus `100`.
- `semester` hanya boleh `1` atau `2`.
