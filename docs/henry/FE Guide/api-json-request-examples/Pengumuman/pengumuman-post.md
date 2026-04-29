# POST /api/administrasi/pengumuman

Auth: `sanctum` (Role: Petugas Admin)

Endpoint ini dipakai untuk membuat pengumuman baru.

## Contoh request body

```json
{
  "judul": "Pengumuman Kelulusan PPDB 2026",
  "konten": "Selamat kepada peserta yang lulus...",
  "kategori": "ppdb",
  "is_aktif": true,
  "is_pinned": false,
  "urutan": 1,
  "tanggal_selesai": "2026-05-30"
}
```

## Catatan

- `kategori` bisa berupa `ppdb`, `akademik`, `umum`.
