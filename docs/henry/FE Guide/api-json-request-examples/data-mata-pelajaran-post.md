# POST /api/administrasi/mata-pelajaran

Auth: `sanctum`

Endpoint ini dipakai untuk membuat data mata pelajaran baru.

## Contoh request body

```json
{
  "kode_mapel": "MA-FIQ-01",
  "nama_mapel": "Fikih",
  "kode_unit": "MA",
  "kelompok_mapel": "WAJIB",
  "urutan": 1,
  "keterangan": "Mapel inti",
  "status": "AKTIF"
}
```

## Catatan

- `kode_mapel` wajib unik pada tabel `data_mata_pelajaran`.
- `kode_unit` harus ada pada tabel `data_unit` jika diisi.
- Backend menormalisasi `kode_mapel`, `kode_unit`, dan `status` ke huruf besar.
