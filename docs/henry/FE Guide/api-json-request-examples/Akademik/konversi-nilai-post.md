# POST /api/akademik/konversi-nilai

Auth: `sanctum`

Endpoint ini dipakai untuk membuat data konversi nilai.

## Contoh request body

```json
{
  "kode_unit": "U01",
  "nilai_min": 90,
  "nilai_max": 100,
  "nilai_huruf": "A",
  "predikat": "Sangat Baik",
  "status": "AKTIF"
}
```

## Catatan

- `status` opsional, default backend adalah `AKTIF`.
- `nilai_max` harus lebih besar atau sama dengan `nilai_min`.
