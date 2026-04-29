# POST /api/administrasi/spp/pembayaran

Auth: `sanctum` (Role: Petugas Admin / Petugas Tata Usaha)

Endpoint ini dipakai untuk mencatat pembayaran baru.

## Contoh request body

```json
{
  "id_santri": 120,
  "id_setting": 5,
  "nominal_bayar": 500000,
  "tanggal_bayar": "2026-04-22 10:00:00",
  "metode_bayar": "transfer"
}
```

## Catatan

- Status default biasanya `pending` sampai diverifikasi.
