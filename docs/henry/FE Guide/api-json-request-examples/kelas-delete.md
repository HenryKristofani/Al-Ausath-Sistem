# DELETE /api/akademik/kelas/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk memindahkan data kelas ke trash.

## Contoh request

```json
{
  "path": {
    "id": 12
  }
}
```

## Contoh response sukses

```json
{
  "message": "Data kelas dipindahkan ke trash."
}
```

## Catatan

- Gunakan `id_kelas` sebagai path parameter `id`.
- Delete biasa tidak menghapus data permanen, hanya menandai `is_deleted = true`.
