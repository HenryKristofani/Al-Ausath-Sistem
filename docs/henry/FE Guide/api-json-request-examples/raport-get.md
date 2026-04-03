# GET /api/akademik/raport

Auth: `sanctum`

Endpoint ini dipakai untuk list rekap raport.

## Contoh request

```json
{
  "query": {
    "q": "2025001",
    "nama": "Ahmad",
    "status": "DRAFT",
    "nomor_induk": "2025001",
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "include_nilai_mapel": true,
    "per_page": 10
  }
}
```

## Catatan

- `include_nilai_mapel=true` akan menambahkan detail nilai mapel ke setiap baris.
- `status` hanya menerima `DRAFT` atau `TERBIT`.
