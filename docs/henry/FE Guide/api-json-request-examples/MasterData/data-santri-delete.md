# DELETE /api/master/data-santri/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus data santri.

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
- Data santri tidak dapat dihapus jika masih dipakai pada data pembayaran SPP atau data terkait lainnya (akan mengembalikan HTTP 422).
