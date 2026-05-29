# 📊 Distribusi Nilai Santri

**Endpoint:** `GET /api/akademik/analytics/score-distribution`

**Purpose:** Menampilkan jumlah santri yang masuk dalam rentang nilai tertentu (90-100, 80-89, 70-79, 60-69, <60) di kelas/mapel yang diampu pengajar.

---

## Request Examples

### Example 1: Distribusi Semua Kelas yang Diajar

```
GET /api/akademik/analytics/score-distribution
Authorization: Bearer {pengajar_token}
```

**Query Parameters:** (none - distribusi global semua kelas-mapel yang diajar)

---

### Example 2: Distribusi Kelas Spesifik

```
GET /api/akademik/analytics/score-distribution?kode_kelas=9-PA&tahun_ajaran=2024-2025&semester=2
Authorization: Bearer {pengajar_token}
```

**Query Parameters:**

- `kode_kelas=9-PA`
- `tahun_ajaran=2024-2025`
- `semester=2`

---

### Example 3: Distribusi Mapel Spesifik di Kelas Spesifik

```
GET /api/akademik/analytics/score-distribution?kode_kelas=9-PA&kode_mapel=MAT001&tahun_ajaran=2024-2025&semester=2
Authorization: Bearer {pengajar_token}
```

**Query Parameters:**

- `kode_kelas=9-PA`
- `kode_mapel=MAT001`
- `tahun_ajaran=2024-2025`
- `semester=2`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "range": "90-100 (A)",
      "min": 90,
      "max": 100,
      "count": 8,
      "percentage": 12.9
    },
    {
      "range": "80-89 (B)",
      "min": 80,
      "max": 89,
      "count": 18,
      "percentage": 29.03
    },
    {
      "range": "70-79 (C)",
      "min": 70,
      "max": 79,
      "count": 22,
      "percentage": 35.48
    },
    {
      "range": "60-69 (D)",
      "min": 60,
      "max": 69,
      "count": 10,
      "percentage": 16.13
    },
    {
      "range": "0-59 (E)",
      "min": 0,
      "max": 59,
      "count": 4,
      "percentage": 6.45
    }
  ],
  "total_santri": 62,
  "filters": {
    "tahun_ajaran": "2024-2025",
    "semester": 2,
    "kode_kelas": null,
    "kode_mapel": null
  }
}
```

### Field Explanation:

| Field | Type | Description |
|-------|------|-------------|
| `range` | string | Label range nilai (e.g., "90-100 (A)") |
| `min` | integer | Nilai minimum range |
| `max` | integer | Nilai maksimum range |
| `count` | integer | Jumlah santri dalam range tersebut |
| `percentage` | float | Persentase santri dari total dalam range tersebut |
| `total_santri` | integer | Total santri yang dianalisis |

---

## Range Grades

| Range | Grade | Description |
|-------|-------|-------------|
| 90-100 | A | Excellent |
| 80-89 | B | Good |
| 70-79 | C | Average |
| 60-69 | D | Below Average |
| 0-59 | E | Fail |

---

## Use Cases

- **Class Distribution**: Lihat sebaran pencapaian nilai di kelas
- **Curve Analysis**: Identifikasi kurva distribusi (normal, left-skewed, right-skewed)
- **Intervention Planning**: Fokus remedial pada santri di range rendah (D, E)
- **Enrichment Planning**: Identifikasi santri excellence (A) untuk enrichment
- **Curriculum Review**: Evaluasi apakah metode mengajar efektif berdasarkan distribusi

---

## Filter Explanation

### `tahun_ajaran`
- Optional
- Format: string (e.g., "2024-2025")
- Jika kosong: menggunakan semua tahun ajaran

### `semester`
- Optional
- Value: 1 atau 2
- Jika kosong: menggunakan semua semester

### `kode_kelas`
- Optional
- Kode unik kelas
- Jika kosong: distribusi aggregated dari semua kelas
- Berguna untuk melihat distribusi per kelas

### `kode_mapel`
- Optional
- Kode unik mata pelajaran
- Jika kosong: distribusi aggregated dari semua mapel
- Berguna untuk melihat distribusi per mapel spesifik

---

## Example Interpretations

### Scenario 1: Normal Distribution (Ideal)
```
90-100: 12.9% (8 santri)
80-89:  29.03% (18 santri) ← Terbanyak
70-79:  35.48% (22 santri) ← Terbanyak
60-69:  16.13% (10 santri)
0-59:   6.45% (4 santri)
```
**Insight**: Distribusi normal, kebanyakan santri di range C-B, ada balance.

### Scenario 2: Left-Skewed (Baik)
```
90-100: 35% (Banyak A dan B)
80-89:  40%
70-79:  20%
60-69:  4%
0-59:   1%
```
**Insight**: Kurva miring kiri, kebanyakan santri mendapat nilai tinggi.

### Scenario 3: Right-Skewed (Perlu Perhatian)
```
90-100: 5%
80-89:  15%
70-79:  25%
60-69:  35% ← Terbanyak di D
0-59:   20% ← Banyak E
```
**Insight**: Kurva miring kanan, banyak santri di bawah rata-rata, perlu remedial.

---

## Notes

- Data difilter dari `DataKelasMapel` berdasarkan `id_petugas` (pengajar yang login)
- Jika tidak ada filter, distribusi adalah aggregated dari semua kelas-mapel yang diajar
- Kombinasi filter semua parameter menghasilkan distribusi sangat spesifik
- Auth required: token pengajar (DataPetugas)
- Range skor fixed: A (90-100), B (80-89), C (70-79), D (60-69), E (0-59)
