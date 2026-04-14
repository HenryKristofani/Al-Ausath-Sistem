# GET /api/administrasi/mata-pelajaran/export

Auth: `sanctum`

Endpoint ini dipakai untuk export data mata pelajaran ke file CSV.

## Contoh request

```json
{
  "query": {
    "kode_unit": "MA",
    "kelompok_mapel": "WAJIB",
    "status": "AKTIF",
    "q": "FIKIH"
  }
}
```

## Catatan

- Respons berupa file CSV (`streamDownload`).
- Mendukung filter yang sama dengan endpoint list.
