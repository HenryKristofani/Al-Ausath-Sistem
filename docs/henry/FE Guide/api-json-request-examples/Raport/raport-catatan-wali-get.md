# GET /api/akademik/raport/catatan-wali

Auth: `sanctum`

Endpoint ini dipakai untuk mengambil catatan wali kelas dan nilai keseharian per santri-semester.

## Contoh request

```json
{
  "query": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- `nomor_induk`, `tahun_ajaran`, dan `semester` wajib diisi.
- Jika data raport belum ada, field catatan dan keseharian akan bernilai `null`.
- Response berisi: `catatan_wali`, `id_wali_kelas`, `keseharian_kebersihan`, `keseharian_kerapian`, `keseharian_keterampilan`.
