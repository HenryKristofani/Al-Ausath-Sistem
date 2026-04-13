# GET /api/master/data-santri

Auth: `sanctum`

Endpoint ini dipakai untuk list data santri.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "status": "AKTIF",
    "kode_kelas": "X-IPA-1",
    "q": "Ahmad"
  }
}
```

## Catatan

- `per_page` opsional (default 10).
- Filter `status` akan mencari santri dengan status tertentu.
- Filter `kode_kelas` akan mencari santri di kelas tertentu.
- Pencarian `q` akan mencari pada `nama_lengkap_santri` dan `nomor_induk`.
- Response akan include relasi `kelas` dan `akun`.
