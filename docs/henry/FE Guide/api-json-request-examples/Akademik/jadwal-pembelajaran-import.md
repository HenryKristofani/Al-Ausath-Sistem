# POST /api/data-master/jadwal-pembelajaran/import

Auth: `sanctum`

Endpoint ini dipakai untuk import jadwal pembelajaran dari file CSV/XLSX.

## Contoh request

```
POST /api/data-master/jadwal-pembelajaran/import
Content-Type: multipart/form-data

file: jadwal.xlsx
```

## Contoh response sukses

```json
{
  "message": "Import data jadwal pembelajaran selesai.",
  "data": {
    "inserted": 3,
    "updated": 1,
    "failed": 0,
    "error_rows": []
  }
}
```
