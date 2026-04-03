# DELETE /api/akademik/bobot/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus bobot nilai.

## Contoh request

```json
{
  "path": {
    "id": 12
  }
}
```

## Catatan

- Tidak ada request body.
- Backend akan menghapus data berdasarkan `id`.
