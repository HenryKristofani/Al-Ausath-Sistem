# PUT /api/akademik/kkm-mapel/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk update data KKM mapel.

## Contoh request body

```json
{
  "kode_mapel": "MATH-01",
  "kode_unit": "U01",
  "tahun_ajaran": "2025/2026",
  "semester": 2,
  "nilai_kkm": 78,
  "keterangan": "Revisi KKM"
}
```

## Catatan

- Semua field bersifat opsional saat update, tetapi kombinasi unik tetap dijaga.
- Role backend bisa mengembalikan `403` untuk hak akses tertentu.
