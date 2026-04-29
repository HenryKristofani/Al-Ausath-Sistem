# GET /api/administrasi/pengumuman

Auth: `sanctum` (Role: Petugas Admin)

Endpoint ini dipakai untuk mendapatkan daftar pengumuman.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kategori": "ppdb",
    "is_aktif": true,
    "q": "libur"
  }
}
```

## Catatan

- Filter bersifat opsional.
