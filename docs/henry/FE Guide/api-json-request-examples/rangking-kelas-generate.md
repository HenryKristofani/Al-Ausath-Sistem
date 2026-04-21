# POST /api/akademik/rangking-kelas/generate

Auth: `sanctum`

Endpoint ini dipakai untuk generate ulang ranking kelas berdasarkan data raport semester berjalan.

## Contoh request body

```json
{
  "kode_kelas": "KLS-10A",
  "tahun_ajaran": "2025/2026",
  "semester": 1
}
```

## Contoh response sukses

```json
{
  "message": "Ranking kelas berhasil digenerate ulang dari data raport terbaru.",
  "data": {
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "total_siswa": 3,
    "generated_at": "2026-04-21 14:30:00",
    "ranking": [
      {
        "peringkat_kelas": 1,
        "total_siswa_kelas": 3,
        "nomor_induk": "2025001",
        "nama_lengkap_santri": "Ahmad Fikri",
        "rata_rata": 94.5,
        "jumlah_nilai": 189.0
      },
      {
        "peringkat_kelas": 2,
        "total_siswa_kelas": 3,
        "nomor_induk": "2025002",
        "nama_lengkap_santri": "Muhammad Rizky",
        "rata_rata": 91.0,
        "jumlah_nilai": 182.0
      },
      {
        "peringkat_kelas": 3,
        "total_siswa_kelas": 3,
        "nomor_induk": "2025003",
        "nama_lengkap_santri": "Siti Aisyah",
        "rata_rata": 88.25,
        "jumlah_nilai": 176.5
      }
    ]
  }
}
```

## Contoh response validasi gagal

```json
{
  "message": "Data raport untuk kelas dan semester ini belum tersedia."
}
```

## Catatan

- `kode_kelas` wajib ada di tabel `data_kelas`.
- Backend akan mengurutkan data berdasarkan `rata_rata`, lalu `jumlah_nilai`, lalu nama santri.
- Response `ranking` sudah mencerminkan urutan yang dipakai saat update `peringkat_kelas`.
