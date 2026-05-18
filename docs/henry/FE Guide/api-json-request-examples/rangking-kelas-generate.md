# POST /api/akademik/rangking-kelas/generate

Auth: `sanctum`

Endpoint ini dipakai untuk generate ulang ranking kelas berdasarkan data raport semester berjalan. Sistem akan mengurutkan santri berdasarkan kriteria skor, kemudian update database secara atomik.

## Validasi Parameter

| Parameter | Tipe | Validasi | Keterangan |
|-----------|------|----------|-----------|
| `kode_kelas` | string | required, max:10, exists:data_kelas | Kode kelas harus ada di tabel `data_kelas` |
| `tahun_ajaran` | string | required, max:20 | Format: "2025/2026" |
| `semester` | integer | required, in:1,2 | Semester 1 atau 2 |

## Contoh Request

```json
{
  "kode_kelas": "KLS-10A",
  "tahun_ajaran": "2025/2026",
  "semester": 1
}
```

## Contoh Response Sukses (HTTP 200)

```json
{
  "message": "Ranking kelas berhasil digenerate ulang dari data raport terbaru.",
  "data": {
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "total_siswa": 3,
    "generated_at": "2026-05-18 14:30:00",
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

## Contoh Response Data Tidak Ditemukan (HTTP 422)

```json
{
  "message": "Data raport untuk kelas dan semester ini belum tersedia."
}
```

## Algoritma Pengurutan

Backend mengurutkan santri menggunakan prioritas berikut (dari tertinggi ke terendah):

1. **`rata_rata`** (skor rata-rata) - descending (nilai terbesar ke terkecil)
2. **`jumlah_nilai`** (total poin) - descending (nilai terbesar ke terkecil)
3. **`nama_lengkap_santri`** (nama santri) - ascending (A-Z, case-insensitive)
4. **`nomor_induk`** (nomor induk santri) - ascending

Urutan yang dihasilkan langsung di-update ke field `peringkat_kelas` dan `total_siswa_kelas` di database.

## Catatan Teknis

- Database update menggunakan batch VALUES query untuk performance dan atomicity
- Field `updated_at` pada tabel `data_raport` akan di-update secara otomatis
- Jika tabel `data_kelas` tidak memiliki `kode_kelas` yang diminta, request akan gagal validasi (HTTP 422)
