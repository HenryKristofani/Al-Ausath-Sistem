# PUT /api/administrasi/ppdb/pendaftar/{id}/verifikasi

Auth: `sanctum` (Role: Petugas Admin / Petugas PPDB)

Endpoint ini dipakai oleh admin untuk melakukan verifikasi data pendaftar.

## Contoh request body

```json
{
  "status_verifikasi": "diterima",
  "catatan": "Dokumen lengkap dan valid"
}
```

## Catatan

- `status_verifikasi` biasanya berupa `diterima` atau `ditolak`.
- Apabila diterima, sistem di backend biasanya akan memproses konfirmasi kelulusan.
