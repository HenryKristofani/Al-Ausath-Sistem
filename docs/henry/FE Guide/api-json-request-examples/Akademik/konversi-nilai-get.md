# GET /api/akademik/konversi-nilai

Auth: `sanctum`

Endpoint ini dipakai untuk list konversi nilai.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kode_unit": "U01",
    "status": "AKTIF"
  }
}
```

## Catatan

- `status` difilter sebagai `AKTIF` atau `NONAKTIF`.
- `kode_unit` mendukung data global jika unit spesifik tidak ada.
