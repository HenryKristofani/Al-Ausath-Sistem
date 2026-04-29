# POST /api/akademik/raport/catatan-wali

Auth: `sanctum`

Endpoint ini dipakai untuk insert atau update catatan wali kelas sekaligus nilai keseharian santri.

## Contoh request body

```json
{
  "nomor_induk": "2025001",
  "kode_kelas": "KLS01",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "catatan_wali": "Anak menunjukkan perkembangan adab dan akademik yang baik.",
  "id_wali_kelas": 12,
  "keseharian_kebersihan": "A",
  "keseharian_kerapian": "B",
  "keseharian_keterampilan": "A"
}
```

## Catatan

- Wajib: `nomor_induk`, `kode_kelas`, `tahun_ajaran`, `semester`, `catatan_wali`.
- Opsional: `id_wali_kelas`, `keseharian_kebersihan`, `keseharian_kerapian`, `keseharian_keterampilan`.
- Endpoint ini memakai mekanisme `updateOrCreate` pada tabel `data_raport` berdasarkan kombinasi `nomor_induk + tahun_ajaran + semester`.
- Untuk nilai keseharian, FE disarankan kirim nilai A/B/C/D sesuai kebijakan client.
