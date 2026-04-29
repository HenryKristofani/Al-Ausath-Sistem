# POST /api/akademik/bobot/set-default

Auth: `sanctum`

Endpoint ini menyimpan bobot default `20/30/50`.

## Contoh request body

```json
{
  "tahun_ajaran": "2025/2026",
  "semester": 1
}
```

## Catatan

- Backend akan membuat atau memperbarui data bobot global.
- Nilai bobot yang diset otomatis adalah `20`, `30`, dan `50`.
