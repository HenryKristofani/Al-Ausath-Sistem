# PUT /api/akademik/konversi-nilai/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk update konversi nilai.

## Contoh request body

```json
{
  "kode_unit": "U01",
  "nilai_min": 85,
  "nilai_max": 89.99,
  "nilai_huruf": "B",
  "predikat": "Baik",
  "status": "AKTIF"
}
```

## Catatan

- Backend akan menolak jika `nilai_max < nilai_min`.
- `status` tetap harus `AKTIF` atau `NONAKTIF`.
