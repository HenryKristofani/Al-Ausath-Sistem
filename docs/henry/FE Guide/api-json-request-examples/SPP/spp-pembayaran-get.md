# GET /api/administrasi/spp/pembayaran

Auth: `sanctum` (Role: Petugas Admin / Petugas Tata Usaha)

Endpoint ini dipakai untuk mendapatkan daftar pembayaran SPP.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "id_santri": 15,
    "status": "pending",
    "tanggal_mulai": "2026-04-01",
    "tanggal_selesai": "2026-04-30"
  }
}
```

## Catatan

- Semua query param bersifat opsional.
