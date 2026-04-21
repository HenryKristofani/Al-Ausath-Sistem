# GET /api/akademik/kelas/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk mengambil detail 1 data kelas berdasarkan `id_kelas`.

## Contoh request

```json
{
  "path": {
    "id": 12
  }
}
```

## Catatan

- Gunakan nilai `id_kelas` sebagai path parameter `id`.
- Response akan include relasi `unit` dan `tahunAjaranRelasi`.
- Response juga memuat count santri aktif, lulus, dan keluar.