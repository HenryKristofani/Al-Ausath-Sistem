# POST /api/akademik/raport/generate

Auth: `sanctum`

Endpoint ini dipakai untuk generate rekap raport status `DRAFT`.

## Contoh request body

```json
{
  "nomor_induk": "2025001",
  "tahun_ajaran": "2025/2026",
  "semester": 1
}
```

## Catatan

- Backend akan menghitung jumlah nilai, rata-rata, absensi, dan ringkasan akhlak.
- Jika data nilai mapel belum lengkap, hasil rekap bisa tetap dibuat tetapi isi perhitungan akan mengikuti data yang tersedia.
