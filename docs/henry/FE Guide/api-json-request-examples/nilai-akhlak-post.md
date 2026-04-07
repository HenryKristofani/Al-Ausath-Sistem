# POST /api/akademik/nilai-akhlak

Auth: `sanctum`

Endpoint ini dipakai untuk insert atau update nilai akhlak.

## Contoh request body

```json
{
  "nomor_induk": "2025001",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "aspek": "AKHLAK",
  "nilai_angka": 92,
  "deskripsi": "Sangat baik dalam adab harian",
  "id_petugas_input": 4
}
```

## Catatan

- `aspek` opsional, default backend adalah `AKHLAK`.
- Endpoint ini memakai mekanisme `updateOrCreate`.
