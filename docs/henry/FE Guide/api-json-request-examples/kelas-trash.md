# GET /api/akademik/kelas/trash

Auth: `sanctum`

Endpoint ini dipakai untuk list data kelas yang sudah dipindahkan ke trash.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kode_unit": "MA",
    "tahun_ajaran": "2025/2026",
    "q": "X-IPA"
  }
}
```

## Catatan

- `per_page` opsional, default `10`.
- Filter `kode_unit` akan dinormalisasi menjadi huruf besar.
- Pencarian `q` akan mencari pada `kode_kelas`, `nama_kelas`, dan `nama_jurusan`.
- Response berupa pagination Laravel dengan data yang berstatus trash.
