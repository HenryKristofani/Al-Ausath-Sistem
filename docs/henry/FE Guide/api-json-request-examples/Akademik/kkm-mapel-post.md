# POST /api/akademik/kkm-mapel

Auth: `sanctum`

Endpoint ini dipakai untuk membuat data KKM mapel.

## Contoh request body

```json
{
  "kode_mapel": "MATH-01",
  "kode_unit": "U01",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "nilai_kkm": 75,
  "keterangan": "KKM semester ganjil"
}
```

## Catatan

- `kode_unit` boleh null.
- Backend menolak duplikasi kombinasi mapel/unit/tahun ajaran/semester.
