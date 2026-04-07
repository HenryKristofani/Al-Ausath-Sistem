# GET /api/akademik/kkm-mapel/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk detail KKM mapel.

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
- Data yang kembali sudah menyertakan relasi mapel dan unit.
