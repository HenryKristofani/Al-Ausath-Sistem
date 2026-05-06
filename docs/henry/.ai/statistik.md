# 📊 Modul Statistik & Analitik Nilai — Implementasi Status

Status Keseluruhan: **✅ SUDAH READY** (Controller + Routes Siap)

---

## 📊 1. Statistik Nilai Santri

**Status:** ✅ DONE

**Endpoint:**

```
GET /api/akademik/nilai-statistik/
```

**Tujuan:** menampilkan gambaran umum performa nilai santri

**Data yang ditampilkan:**

- Nilai rata-rata keseluruhan santri
- Nilai tertinggi
- Nilai terendah
- Jumlah santri yang sudah dinilai
- Total nilai records

**Filter yang tersedia:**

- `kode_kelas` - Per kelas
- `kode_mapel` - Per mata pelajaran
- `tahun_ajaran` - Per tahun ajaran
- `semester` - Per semester (1 atau 2)

**Output Response:**

```json
{
  "data": {
    "rata_rata": 75.50,
    "nilai_tertinggi": 98,
    "nilai_terendah": 45,
    "jumlah_santri": 32,
    "total_nilai": 256
  },
  "filters": { ... }
}
```

**UI:** Card statistik (4 kotak ringkasan)

---

## 📊 2. Rata-rata Nilai per Kelas

**Status:** ✅ DONE

**Endpoint:**

```
GET /api/akademik/nilai-statistik/per-kelas
```

**Tujuan:** melihat performa tiap kelas

**Data yang ditampilkan:**

- Nama kelas
- Rata-rata nilai kelas
- Jumlah santri dalam kelas
- Kode kelas

**Filter yang tersedia:**

- `tahun_ajaran` - Per tahun ajaran
- `semester` - Per semester (1 atau 2)
- `kode_mapel` - Per mata pelajaran (opsional, untuk filter spesifik)

**Output Response:**

```json
{
  "data": [
    {
      "kode_kelas": "9-PA",
      "nama_kelas": "Kelas 9 PAI",
      "rata_rata": 78.25,
      "jumlah_santri": 28
    },
    ...
  ],
  "filters": { ... }
}
```

**Bentuk UI:**

- Tabel / bar chart
- Sorted by rata_rata (tertinggi ke terendah)

**Insight:**

- Kelas dengan performa terbaik
- Kelas yang perlu evaluasi

---

## 📈 3. Grafik Perkembangan Nilai (Trend per Semester)

**Status:** ✅ DONE

**Endpoint:**

```
GET /api/akademik/nilai-statistik/trend
```

**Tujuan:** tracking perkembangan akademik

**Data yang ditampilkan:**

- Nilai per semester
- Rata-rata, tertinggi, terendah per semester
- Jumlah santri per semester

**Filter yang tersedia:**

- `nomor_induk` - Per santri (opsional)
- `kode_kelas` - Per kelas (opsional)
- `kode_mapel` - Per mata pelajaran (opsional)
- `tahun_ajaran` - Per tahun ajaran (opsional)

**Output Response:**

```json
{
  "data": [
    {
      "semester": 1,
      "rata_rata": 74.50,
      "tertinggi": 95,
      "terendah": 50,
      "jumlah_santri": 32
    },
    {
      "semester": 2,
      "rata_rata": 76.25,
      "tertinggi": 98,
      "terendah": 48,
      "jumlah_santri": 30
    }
  ],
  "filters": { ... }
}
```

**Bentuk Grafik:**

- Line chart (paling cocok)

**Sumbu:**

- X: Semester
- Y: Nilai rata-rata

**Insight:**

- Naik / turun performa
- Konsistensi belajar

---

## 🧠 4. Identifikasi Santri Berprestasi

**Status:** ✅ DONE

**Endpoint:**

```
GET /api/akademik/nilai-statistik/berprestasi
```

**Tujuan:** mengidentifikasi santri dengan performa tinggi

**Kriteria (dari logika sistem):**

- Nilai rata-rata tinggi ≥ threshold (default: 85)
- Configurable via query param `threshold`

**Filter yang tersedia:**

- `kode_kelas` - Per kelas (opsional)
- `tahun_ajaran` - Per tahun ajaran (opsional)
- `semester` - Per semester (opsional)
- `threshold` - Nilai minimum untuk dianggap berprestasi (default: 85)
- `limit` - Jumlah top performers (default: 10, max: 100)

**Output Response:**

```json
{
  "data": [
    {
      "nomor_induk": "001",
      "rata_rata": 88.75,
      "mapel_count": 8,
      "nilai_detail": [
        {
          "kode_mapel": "MAPEL-001",
          "nilai_akhir": 90,
          "nilai_tampil": 90,
          "status_ketuntasan": "TUNTAS"
        },
        ...
      ]
    },
    ...
  ],
  "count": 5,
  "filters": { ... }
}
```

**UI Output:**

- List santri terbaik
- Bisa menampilkan top 5 / top 10
- Detail nilai per mapel

---

## 🧠 5. Identifikasi Santri Perlu Bimbingan

**Status:** ✅ DONE

**Endpoint:**

```
GET /api/akademik/nilai-statistik/perlu-bimbingan
```

**Tujuan:** mengidentifikasi santri yang memerlukan perhatian khusus

**Kriteria:**

- `status_ketuntasan = 'BELUM TUNTAS'` (nilai < KKM)
- ATAU nilai rata-rata < threshold (default: 65)

**Filter yang tersedia:**

- `kode_kelas` - Per kelas (opsional)
- `tahun_ajaran` - Per tahun ajaran (opsional)
- `semester` - Per semester (opsional)
- `threshold` - Nilai minimum untuk dianggap butuh bimbingan (default: 65)
- `limit` - Jumlah santri yang ditampilkan (default: 50, max: 500)

**Output Response:**

```json
{
  "data": [
    {
      "nomor_induk": "015",
      "rata_rata": 58.50,
      "mapel_perlu_bimbingan": 5,
      "mapel_belum_tuntas": 3,
      "mapel_detail": [
        {
          "kode_mapel": "MAPEL-002",
          "nilai_akhir": 45,
          "nilai_tampil": 50,
          "status_ketuntasan": "BELUM TUNTAS",
          "flag_warna": "MERAH"
        },
        ...
      ]
    },
    ...
  ],
  "count": 8,
  "filters": { ... }
}
```

**UI Output:**

- List santri yang perlu perhatian
- Disertai mata pelajaran yang lemah
- Flag warna untuk visual indicator (MERAH = nilai < 50)
- Informasi mapel tuntas vs belum tuntas

---

## 🔐 Security

**Middleware:** `auth:sanctum`

**Role yang bisa akses:**

- Admin
- Pengajar (untuk data kelas/mapel yang diajar)

---

## 📝 Notes

- Semua endpoint support pagination-ready response untuk future UI development
- Nilai dibulatkan 2 desimal (menggunakan `round()`)
- Filter bersifat optional - bisa dipanggil tanpa filter untuk data global
- Status ketuntasan case-insensitive (dipakai di PHP untuk safety)
- PostgreSQL-compatible query (using single quotes untuk string literals)
