# GET /api/akademik/kelas

Auth: `sanctum`

Endpoint ini dipakai untuk list data kelas.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kode_unit": "MA",
    "tahun_ajaran": "2025/2026",
    "status": "AKTIF",
    "status_ppdb": "AKTIF",
    "q": "X-IPA"
  }
}
```

## Catatan

- `per_page` opsional, default `10`.
- Filter `kode_unit`, `status`, dan `status_ppdb` akan dinormalisasi menjadi huruf besar.
- Filter `tahun_ajaran` dipakai untuk melihat kelas pada tahun ajaran tertentu.
- Pencarian `q` akan mencari pada `kode_kelas`, `nama_kelas`, dan `nama_jurusan`.
- Response akan memuat pagination Laravel, relasi `unit` dan `tahunAjaranRelasi`, plus `summary_global`.
