# 🎯 Progress Tracking Akademik Pribadi

**Endpoint:** `GET /api/akademik/santri-analytics/academic-progress`

**Purpose:** Menampilkan perbandingan nilai santri terhadap KKM per mata pelajaran semester saat ini, serta perubahan nilai dibanding semester sebelumnya (naik/turun/tetap).

---

## Request Examples

### Example 1: Progress Semester Terbaru (Auto-detect)

```
GET /api/akademik/santri-analytics/academic-progress
Authorization: Bearer {santri_token}
```

**Query Parameters:** (none - otomatis ambil semester tertinggi dan bandingkan dengan semester sebelumnya)

---

### Example 2: Progress Semester Spesifik

```
GET /api/akademik/santri-analytics/academic-progress?semester=2
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `semester=2` (1 atau 2)

---

### Example 3: Progress Tahun Ajaran Spesifik

```
GET /api/akademik/santri-analytics/academic-progress?tahun_ajaran=2024-2025&semester=2
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `tahun_ajaran=2024-2025`
- `semester=2`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "kode_mapel": "MAT001",
      "nama_mapel": "Matematika",
      "nilai_akhir": 86.5,
      "kkm": 70,
      "tuntas": true,
      "status_ketuntasan": "TUNTAS",
      "perubahan": {
        "nilai_sebelumnya": 78,
        "selisih": 8.5,
        "persentase_perubahan": 10.9,
        "trend": "naik"
      }
    },
    {
      "kode_mapel": "IPA001",
      "nama_mapel": "Ilmu Pengetahuan Alam",
      "nilai_akhir": 72,
      "kkm": 70,
      "tuntas": true,
      "status_ketuntasan": "TUNTAS",
      "perubahan": {
        "nilai_sebelumnya": 75,
        "selisih": -3,
        "persentase_perubahan": -4,
        "trend": "turun"
      }
    },
    {
      "kode_mapel": "IND001",
      "nama_mapel": "Bahasa Indonesia",
      "nilai_akhir": 68,
      "kkm": 70,
      "tuntas": false,
      "status_ketuntasan": "BELUM TUNTAS",
      "perubahan": {
        "nilai_sebelumnya": 70,
        "selisih": -2,
        "persentase_perubahan": -2.86,
        "trend": "turun"
      }
    },
    {
      "kode_mapel": "ENG001",
      "nama_mapel": "Bahasa Inggris",
      "nilai_akhir": 80,
      "kkm": 70,
      "tuntas": true,
      "status_ketuntasan": "TUNTAS",
      "perubahan": {
        "nilai_sebelumnya": null,
        "selisih": null,
        "persentase_perubahan": null,
        "trend": "N/A"
      }
    }
  ],
  "summary": {
    "total_mapel": 4,
    "tuntas": 3,
    "belum_tuntas": 1,
    "persentase_tuntas": 75
  },
  "filters": {
    "tahun_ajaran": "2024-2025",
    "semester": 2
  }
}
```

### Field Explanation:

| Field | Type | Description |
|-------|------|-------------|
| `kode_mapel` | string | Kode unik mata pelajaran |
| `nama_mapel` | string | Nama mata pelajaran |
| `nilai_akhir` | float | Nilai akhir semester saat ini |
| `kkm` | float | Kriteria Ketuntasan Minimal (KKM) |
| `tuntas` | boolean | Apakah nilai >= KKM |
| `status_ketuntasan` | string | Status tuntas (TUNTAS / BELUM TUNTAS) |
| `perubahan.nilai_sebelumnya` | float \| null | Nilai di semester sebelumnya (null jika tidak ada) |
| `perubahan.selisih` | float \| null | Delta nilai (current - previous) |
| `perubahan.persentase_perubahan` | float \| null | Persentase perubahan dari semester lalu |
| `perubahan.trend` | string | Status trend: "naik", "turun", "tetap", atau "N/A" |
| `summary.total_mapel` | integer | Total mata pelajaran semester ini |
| `summary.tuntas` | integer | Jumlah mapel yang tuntas (nilai >= KKM) |
| `summary.belum_tuntas` | integer | Jumlah mapel belum tuntas |
| `summary.persentase_tuntas` | float | Persentase ketuntasan |

---

## Response Explanation

### Case 1: Naik (trend = "naik")
- `nilai_sebelumnya: 78` → Matematika semester 1 = 78
- `nilai_akhir: 86.5` → Matematika semester 2 = 86.5
- `selisih: 8.5` → 86.5 - 78 = naik 8.5 poin
- `persentase_perubahan: 10.9` → (8.5 / 78) × 100 = naik 10.9%

### Case 2: Turun (trend = "turun")
- `nilai_sebelumnya: 75` → IPA semester 1 = 75
- `nilai_akhir: 72` → IPA semester 2 = 72
- `selisih: -3` → 72 - 75 = turun 3 poin
- `persentase_perubahan: -4` → (-3 / 75) × 100 = turun 4%

### Case 3: Baru (trend = "N/A")
- `nilai_sebelumnya: null` → Mapel baru, belum ada di semester sebelumnya
- `perubahan.trend: "N/A"` → Tidak ada perbandingan

### Summary
- **Total Mapel**: 4 mapel diambil semester ini
- **Tuntas**: 3 mapel mendapat nilai >= KKM
- **Belum Tuntas**: 1 mapel di bawah KKM
- **Persentase Tuntas**: 75% (3/4)

---

## Use Cases

- **Monitoring Tuntas**: Lihat apakah santri sudah mencapai KKM di semua mapel
- **Trend Analisis**: Identifikasi mapel yang sedang trending naik atau turun
- **Intervention Planning**: Fokus pada mapel belum tuntas untuk remedial
- **Achievement Tracking**: Lihat progress rate semester ke semester

---

## Notes

- Auto-detect semester: Jika `semester` tidak diberikan, ambil semester tertinggi, bandingkan dengan semester sebelumnya
- Semester 1 + previous semester: Hanya bandingkan dengan semester 1 (tidak ada semester 0)
- Jika mapel baru di semester saat ini (tidak ada di semester sebelumnya) → trend = "N/A"
- Auth required: token santri (DataAkunSantri)
