# GET /api/administrasi/ppdb/pendaftar

Auth: `sanctum` (Role: Petugas Admin / Petugas PPDB)

Endpoint ini dipakai untuk mendapatkan daftar pendaftar PPDB.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "status_verifikasi": "pending",
    "jenjang": "MTS",
    "q": "budi"
  }
}
```

## Catatan

- `per_page`, `status_verifikasi`, `jenjang`, `q` bersifat opsional.
- `status_verifikasi` bisa berupa `pending`, `diterima`, `ditolak`, `lulus`.
