# GET /api/administrasi/mata-pelajaran/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk mengambil detail 1 data mata pelajaran berdasarkan `id_mapel`.

## Contoh request

```json
{
  "params": {
    "id": 1
  }
}
```

## Catatan

- Gunakan nilai `id_mapel` sebagai path parameter `id`.
