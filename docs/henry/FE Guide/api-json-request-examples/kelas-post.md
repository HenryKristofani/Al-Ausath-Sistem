# POST /api/akademik/kelas

Auth: `sanctum`

Endpoint ini dipakai untuk membuat data kelas baru.

## Contoh request body

```json
{
  "kode_unit": "MA",
  "kode_kelas": "X-IPA-1",
  "nama_kelas": "X IPA 1",
  "nama_jurusan": "IPA",
  "tahun_ajaran": "2025/2026",
  "status": "AKTIF",
  "status_ppdb": "AKTIF"
}
```

## Catatan

- `kode_unit` wajib ada pada tabel `data_unit`.
- `kode_kelas` harus unik pada data aktif.
- `tahun_ajaran` wajib ada pada tabel `data_tahun_ajaran` yang tidak dihapus.
- Backend akan menormalisasi `kode_unit`, `kode_kelas`, `status`, dan `status_ppdb` menjadi huruf besar.
- Response sukses mengembalikan `data` kelas yang sudah dibuat.