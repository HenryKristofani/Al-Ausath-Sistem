# DELETE /api/akademik/konversi-nilai/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus konversi nilai.

## Contoh request

```json
{
  "path": {
    "id": 3
  }
}
```

## Catatan

- Tidak ada request body.
- Data dihapus berdasarkan `id`.
