# GET /api/akademik/nilai-mapel/{kode_mapel}

Auth: `sanctum`

Endpoint ini dipakai untuk detail nilai mapel per santri dan mapel.

## Contoh request

```json
{
  "path": {
    "kode_mapel": "MATH-01"
  },
  "query": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Catatan

- `nomor_induk` wajib dikirim di query.
- `tahun_ajaran` dan `semester` opsional, tetapi sangat disarankan supaya data yang diambil spesifik.

## Contoh response sukses

```json
{
  "data": {
    "id_nilai": 101,
    "nomor_induk": "2025001",
    "kode_mapel": "MATH-01",
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "nilai_harian": "90.00",
    "nilai_uts": "87.00",
    "nilai_uas": "91.00",
    "nilai_akhir_mapel": "89.60",
    "nilai_rapor_tampil": "90.00",
    "flag_warna_rapor": "HITAM",
    "status_ketuntasan": "TUNTAS"
  }
}
```
