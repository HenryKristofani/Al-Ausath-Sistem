# Raport API Examples — B1 & B2 Payload

> Dokumentasi endpoint raport dengan data konversi nilai huruf dan flag warna konsisten.
> Implementasi: B1 (Konversi Nilai Huruf), B2 (Konsistensi Flag Warna)

---

## 1. GET /api/akademik/raport — List Raport

**Deskripsi:** Daftar rapor dengan opsional include nilai mapel + konversi.

**Request:**

```
GET /api/akademik/raport?include_nilai_mapel=true&status=TERBIT&per_page=10
Authorization: Bearer {token}
```

**Response:**

```json
{
  "data": [
    {
      "id_raport": 1,
      "nomor_induk": "001",
      "nama_lengkap_santri": "Ahmad Wijaya",
      "kode_kelas": "MTQ-A",
      "tahun_ajaran": "2025/2026",
      "semester": 1,
      "jumlah_nilai": 800.5,
      "rata_rata": 86.78,
      "peringkat_kelas": 2,
      "total_siswa_kelas": 25,
      "hadir": 92,
      "sakit": 2,
      "izin": 1,
      "alpha": 0,
      "keseharian_kebersihan": "A",
      "keseharian_kerapian": "A",
      "keseharian_keterampilan": "B",
      "status_raport": "TERBIT",
      "tanggal_terbit": "2026-03-30",
      "nilai_mapel": [
        {
          "kode_mapel": "QUR001",
          "nama_mapel": "Al-Qur'an",
          "kelompok_mapel": "HIFZH",
          "nilai_harian": 85.0,
          "nilai_uts": 84.5,
          "nilai_uas": 88.0,
          "nilai_akhir_mapel": 86.1,
          "nilai_rapor_tampil": 86,
          "flag_warna_rapor": "HITAM",
          "nilai_huruf": "A",
          "predikat": "Sangat Baik"
        },
        {
          "kode_mapel": "MAT001",
          "nama_mapel": "Matematika",
          "kelompok_mapel": "UMUM",
          "nilai_harian": 76.0,
          "nilai_uts": 78.5,
          "nilai_uas": 82.0,
          "nilai_akhir_mapel": 79.7,
          "nilai_rapor_tampil": 80,
          "flag_warna_rapor": "HITAM",
          "nilai_huruf": "B",
          "predikat": "Baik"
        },
        {
          "kode_mapel": "IPA001",
          "nama_mapel": "IPA",
          "kelompok_mapel": "UMUM",
          "nilai_harian": 45.0,
          "nilai_uts": 48.0,
          "nilai_uas": 50.0,
          "nilai_akhir_mapel": 48.4,
          "nilai_rapor_tampil": 50,
          "flag_warna_rapor": "MERAH",
          "nilai_huruf": null,
          "predikat": null
        }
      ]
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "from": 1, "to": 1, "total": 1, "per_page": 10 }
}
```

---

## 2. GET /api/akademik/raport/show — Detail Raport

**Deskripsi:** Detail rapor dengan nilai mapel + konversi untuk petugas.

**Request:**

```
GET /api/akademik/raport/show?nomor_induk=001&tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token_petugas}
```

**Response:**

```json
{
  "message": "Detail rapor berhasil diambil.",
  "data": {
    "raport": {
      "id_raport": 1,
      "nomor_induk": "001",
      "kode_kelas": "MTQ-A",
      "tahun_ajaran": "2025/2026",
      "semester": 1,
      "rata_rata": 86.78,
      "peringkat_kelas": 2,
      "status_raport": "TERBIT",
      "tanggal_terbit": "2026-03-30"
    },
    "santri": {
      "nominal_induk": "001",
      "nama_lengkap_santri": "Ahmad Wijaya",
      "kode_kelas": "MTQ-A"
    },
    "nilai_mapel": [
      {
        "kode_mapel": "QUR001",
        "nama_mapel": "Al-Qur'an",
        "nilai_rapor_tampil": 86,
        "flag_warna_rapor": "HITAM",
        "nilai_huruf": "A",
        "predikat": "Sangat Baik"
      },
      {
        "kode_mapel": "IPA001",
        "nama_mapel": "IPA",
        "nilai_rapor_tampil": 50,
        "flag_warna_rapor": "MERAH",
        "nilai_huruf": null,
        "predikat": null
      }
    ],
    "nilai_akhlak": [
      {
        "aspek": "Jujur",
        "nilai_angka": 85,
        "deskripsi": "Santri menunjukkan kejujuran dalam setiap tindakan."
      }
    ]
  }
}
```

---

## 3. GET /api/akademik/raport/self — Self-Service Santri (Detail)

**Deskripsi:** Lihat rapor milik santri sendiri dengan nilai mapel + konversi.

**Request:**

```
GET /api/akademik/raport/self?tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token_santri}
```

**Response:**

```json
{
  "message": "Rapor berhasil diambil.",
  "data": {
    "raport": {
      "id_raport": 1,
      "nomor_induk": "001",
      "kode_kelas": "MTQ-A",
      "tahun_ajaran": "2025/2026",
      "semester": 1,
      "status_raport": "TERBIT",
      "rata_rata": 86.78
    },
    "santri": {
      "nomor_induk": "001",
      "nama_lengkap_santri": "Ahmad Wijaya"
    },
    "nilai_mapel": [
      {
        "kode_mapel": "QUR001",
        "nama_mapel": "Al-Qur'an",
        "nilai_rapor_tampil": 86,
        "flag_warna_rapor": "HITAM",
        "nilai_huruf": "A",
        "predikat": "Sangat Baik"
      }
    ],
    "nilai_akhlak": [
      {
        "aspek": "Jujur",
        "nilai_angka": 85,
        "deskripsi": "..."
      }
    ]
  }
}
```

---

## 4. GET /api/akademik/raport/pdf — Download PDF (Petugas)

**Deskripsi:** Generate dan download PDF rapor dengan nilai huruf + flag warna.

**Request:**

```
GET /api/akademik/raport/pdf?nomor_induk=001&tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token_petugas}
```

**Response:** PDF file (application/pdf)

- Header: "LAPORAN HASIL BELAJAR SANTRI"
- Section A: Nilai Mata Pelajaran (kolom: No, Mata Pelajaran, Harian, Ulangan, Ujian, Akhir, Nilai Rapor, Warna)
  - Nilai rapor ditampilkan dengan warna:
    - HITAM (normal): `86`, `80`, dst.
    - MERAH: `50` (nilai asli < 50)
- Section B: Kehadiran
- Section C: Akhlak dan Catatan
  - Diperlihatkan nilai akhlak per aspek
- Section D: Catatan Wali Kelas
- Watermark: "DRAFT" (jika status DRAFT), tanpa watermark (jika status TERBIT)

---

## 5. GET /api/akademik/raport/self/pdf — Download PDF (Self-Service)

**Deskripsi:** Santri download PDF rapor milik sendiri.

**Request:**

```
GET /api/akademik/raport/self/pdf?tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token_santri}
```

**Response:** PDF file (sama struktur dengan #4)

---

## Skenario Edge Case — B2 Testing

### Skenario 1: Nilai Asli < 50 → 50 MERAH

```json
{
  "nilai_harian": 40,
  "nilai_uts": 45,
  "nilai_uas": 48,
  "nilai_akhir_mapel": 44.8,
  "nilai_rapor_tampil": 50,
  "flag_warna_rapor": "MERAH",
  "nilai_huruf": null
}
```

### Skenario 2: Nilai Asli = 50 → 50 HITAM

```json
{
  "nilai_harian": 50,
  "nilai_uts": 50,
  "nilai_uas": 50,
  "nilai_akhir_mapel": 50.0,
  "nilai_rapor_tampil": 50,
  "flag_warna_rapor": "HITAM",
  "nilai_huruf": "C",
  "predikat": "Cukup"
}
```

### Skenario 3: Nilai Asli = 100 → 98 HITAM

```json
{
  "nilai_harian": 100,
  "nilai_uts": 100,
  "nilai_uas": 100,
  "nilai_akhir_mapel": 100.0,
  "nilai_rapor_tampil": 98,
  "flag_warna_rapor": "HITAM",
  "nilai_huruf": "A",
  "predikat": "Sangat Baik"
}
```

### Skenario 4: Pembulatan 86.6 → 87 HITAM

```json
{
  "nilai_akhir_mapel": 86.6,
  "nilai_rapor_tampil": 87,
  "flag_warna_rapor": "HITAM",
  "nilai_huruf": "A",
  "predikat": "Sangat Baik"
}
```

### Skenario 5: Pembulatan 49.4 → 49 → clamp → 50 MERAH

```json
{
  "nilai_akhir_mapel": 49.4,
  "nilai_rapor_tampil": 50,
  "flag_warna_rapor": "MERAH",
  "nilai_huruf": null
}
```

---

## Catatan Konversi Nilai Huruf (B1)

**Prioritas Fallback Unit → Global:**

1. Query konversi dengan `kode_unit` = unit kelas santri
2. Jika tidak ada, fallback ke `kode_unit = null` (global)
3. Hanya status `AKTIF` yang diambil
4. Match berdasarkan rentang: jika `nilai_rapor_tampil` >= `nilai_min` AND <= `nilai_max`

**Contoh Konversi Tabel:**
| nilai_min | nilai_max | nilai_huruf | predikat | status |
|---|---|---|---|---|
| 85 | 100 | A | Sangat Baik | AKTIF |
| 75 | 84 | B | Baik | AKTIF |
| 65 | 74 | C | Cukup | AKTIF |
| 50 | 64 | D | Kurang | AKTIF |
| 0 | 49 | E | Sangat Kurang | AKTIF |

---

## Backfill Data Lama

**Command:**

```bash
# Dry-run (test tanpa update)
php artisan raport:backfill-nilai-mapel --dry-run

# Eksekusi (update nyata)
php artisan raport:backfill-nilai-mapel
```

**Logika:**

- Cari source nilai mentah dari `nilai_akhir_mapel` atau fallback ke komponen (20/30/50)
- Terapkan aturan normalisasi sama dengan controller
- Update kolom: `nilai_akhir_mapel`, `nilai_rapor_tampil`, `flag_warna_rapor`
- Output statistik: processed, updated, skipped

---

## Status Validasi Backend

✅ **Lengkap:**

- B1: Konversi nilai huruf + predikat (dengan fallback unit/global)
- B2: Konsistensi flag warna (dari nilai asli < 50)
- Edge case: 49.x, 50 asli, 100 → handled
- Backfill command: tersedia dengan dry-run

✅ **Endpoint Raport Ready:**

- GET /api/akademik/raport (list + optional detail mapel)
- GET /api/akademik/raport/show (detail per raport)
- GET /api/akademik/raport/self (santri lihat milik sendiri)
- GET /api/akademik/raport/pdf (download PDF petugas)
- GET /api/akademik/raport/self/pdf (download PDF santri self-service)
