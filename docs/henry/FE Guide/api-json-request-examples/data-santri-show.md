# GET /api/master/data-santri/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk mengambil detail 1 data santri berdasarkan `id_santri`.

## Contoh request

```json
{
  "params": {
    "id": 1
  }
}
```

## Catatan

- Gunakan nilai `id_santri` sebagai path parameter `id`.
- Response akan include relasi `kelas` dan `akun`.
