# POST /api/akademik/raport/publish

Auth: `sanctum`

Endpoint ini dipakai untuk mengubah status raport menjadi `TERBIT`.

## Contoh request body

```json
{
  "kode_kelas": "KLS-10A",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "nomor_induk": "2025001",
  "tanggal_terbit": "2026-04-03"
}
```

## Catatan

- `nomor_induk` opsional. Jika dikirim, publish hanya untuk santri tersebut.
- Jika `tanggal_terbit` tidak dikirim, backend memakai tanggal hari ini.
