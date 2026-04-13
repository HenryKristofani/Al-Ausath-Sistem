# GET /api/master/data-santri/options

Auth: `sanctum`

Endpoint ini dipakai untuk autocomplete dropdown santri di FE dengan response ringan.

## Contoh request

```json
{
  "query": {
    "q": "ahmad",
    "limit": 20
  }
}
```

## Contoh response

```json
[
  {
    "id_santri": 12,
    "nomor_induk": "001",
    "nama_lengkap_santri": "Ahmad Hidayat"
  }
]
```

## Catatan

- `q` opsional, mencari pada `nomor_induk` dan `nama_lengkap_santri`.
- `limit` opsional (default 20, maksimal 50).
- Response hanya berisi field ringan: `id_santri`, `nomor_induk`, `nama_lengkap_santri`.
