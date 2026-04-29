# DELETE /api/administrasi/mata-pelajaran/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus data mata pelajaran.

## Contoh request

```json
{
  "params": {
    "id": 1
  }
}
```

## Catatan

- Data tidak bisa dihapus jika masih direferensikan oleh data lain (mis. kelas-mapel/KKM).
