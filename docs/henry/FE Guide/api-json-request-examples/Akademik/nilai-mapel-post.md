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
- Backend menyimpan `status_ketuntasan` ke tabel `data_nilai_siswa` berdasarkan perbandingan `nilai_akhir_mapel` terhadap KKM.

## Contoh response sukses

```json
{
  "message": "Komponen nilai mapel berhasil disimpan.",
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
  },
  "perhitungan": {
    "kkm": {
      "nilai_kkm": 75,
      "status": "TUNTAS"
    }
  }
}
```
