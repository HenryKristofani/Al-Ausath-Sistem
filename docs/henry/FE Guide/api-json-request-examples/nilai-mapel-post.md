# POST /api/akademik/nilai-mapel

Auth: `sanctum`

Endpoint ini dipakai untuk simpan komponen nilai mapel.

## Contoh request body

```json
{
  "nomor_induk": "2025001",
  "kode_mapel": "MATH-01",
  "kode_kelas": "KLS-10A",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "id_petugas_input": 4,
  "keterangan": "Input nilai tengah semester",
  "tugas": [
    { "nilai": 90, "jenis": "PR" },
    { "nilai": 88, "jenis": "TUGAS_PENGGANTI" },
    { "nilai": 92, "jenis": "MODUL_KOMPETENSI" }
  ],
  "ulangan": [
    { "nilai": 85, "soal_disusun_pengajar": true, "diawasi_pengajar": true },
    { "nilai": 87, "soal_disusun_pengajar": true, "diawasi_pengajar": true },
    { "nilai": 89, "soal_disusun_pengajar": true, "diawasi_pengajar": true }
  ],
  "ujian_akhir": 91
}
```

## Catatan

- `tugas` minimal 3 item.
- `ulangan` minimal 3 item dan semua item valid harus bernilai `true` untuk dua flag pengawasan.
- Backend akan menolak jika `kode_kelas` tidak sesuai dengan `nomor_induk` santri.
