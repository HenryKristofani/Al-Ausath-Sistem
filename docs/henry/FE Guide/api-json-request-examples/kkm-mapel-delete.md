# DELETE /api/akademik/kkm-mapel/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus data KKM mapel.

## Contoh request

```json
{
  "path": {
    "id": 7
  }
}
```

## Catatan

- Tidak ada request body.
- Aksi ini bisa ditolak backend jika role tidak sesuai.
