# DELETE /api/akademik/nilai-mapel/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus nilai mapel berdasarkan ID.

## Contoh request

```json
{
  "path": {
    "id": 15
  }
}
```

## Catatan

- Tidak ada request body.
- Gunakan `id` dari `id_nilai` pada data nilai mapel.
- Jika ID tidak ditemukan, backend akan mengembalikan 404.
