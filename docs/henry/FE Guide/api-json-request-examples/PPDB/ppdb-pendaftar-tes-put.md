# PUT /api/administrasi/ppdb/pendaftar/{id}/tes

Auth: `sanctum` (Role: Petugas Admin / Petugas PPDB)

Endpoint ini dipakai untuk merekam atau memperbarui hasil tes pendaftar.

## Contoh request body

```json
{
  "nilai": 85.50,
  "status_tes": "lulus",
  "metode_tes": "online",
  "catatan": "Nilai tes sangat baik"
}
```

## Catatan

- `status_tes` dapat berupa `lulus` atau `tidak_lulus`.
