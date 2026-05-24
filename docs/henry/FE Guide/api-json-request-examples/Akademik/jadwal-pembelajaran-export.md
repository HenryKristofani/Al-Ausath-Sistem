# GET /api/data-master/jadwal-pembelajaran/export

Auth: `sanctum`

Endpoint ini dipakai untuk export jadwal pembelajaran ke file XLSX.

## Query params

- `id_kelas_mapel` (int)
- `tahun_ajaran` (string)
- `hari` (string)
- `status` (string)
- `q` (string) kata kunci

## Contoh request

```
GET /api/data-master/jadwal-pembelajaran/export?tahun_ajaran=2024/2025&hari=SENIN
```

## Contoh response sukses

- File `data-jadwal-pembelajaran-YYYYMMDD_HHMMSS.xlsx`
