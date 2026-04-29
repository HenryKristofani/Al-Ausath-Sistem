# GET /api/administrasi/mata-pelajaran

Auth: `sanctum`

Endpoint ini dipakai untuk list data mata pelajaran.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kode_unit": "MA",
    "kelompok_mapel": "WAJIB",
    "status": "AKTIF",
    "q": "FIKIH"
  }
}
```

## Catatan

- `per_page` opsional (default 10).
- Filter `kode_unit`, `status` akan dinormalisasi menjadi huruf besar.
- Pencarian `q` akan mencari pada `kode_mapel`, `nama_mapel`, dan `kelompok_mapel`.
