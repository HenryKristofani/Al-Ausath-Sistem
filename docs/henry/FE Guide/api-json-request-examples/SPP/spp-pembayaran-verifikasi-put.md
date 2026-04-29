# PUT /api/administrasi/spp/pembayaran/{id}/verifikasi

Auth: `sanctum` (Role: Petugas Admin / Petugas Tata Usaha)

Endpoint ini dipakai untuk memverifikasi pembayaran yang berstatus pending.

## Contoh request body

```json
{
  "status": "verified"
}
```

## Catatan

- Setelah diverifikasi, biasanya sistem akan membuatkan kwitansi/mengesahkan pembayaran.
