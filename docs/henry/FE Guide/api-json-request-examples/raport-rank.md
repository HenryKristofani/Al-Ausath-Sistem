# POST /api/akademik/raport/rank

Auth: `sanctum`

Endpoint ini dipakai untuk menghitung ranking kelas.

## Contoh request body

```json
{
  "kode_kelas": "KLS-10A",
  "tahun_ajaran": "2025/2026",
  "semester": 1
}
```

## Catatan

- `kode_kelas` wajib valid.
- Backend akan mengisi peringkat untuk semua santri di kelas tersebut.
