# 📈 Grafik Perkembangan Nilai Semester ke Semester

**Endpoint:** `GET /api/akademik/santri-analytics/scores-trend`

**Purpose:** Menampilkan riwayat nilai akhir per mata pelajaran di setiap semester, cocok untuk visualisasi line chart tren perkembangan akademik santri.

---

## Request Examples

### Example 1: Tren Semua Mata Pelajaran

```
GET /api/akademik/santri-analytics/scores-trend
Authorization: Bearer {santri_token}
```

**Query Parameters:** (none - menampilkan semua mapel)

---

### Example 2: Tren Mata Pelajaran Spesifik

```
GET /api/akademik/santri-analytics/scores-trend?kode_mapel=MAT001
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `kode_mapel=MAT001`

---

### Example 3: Tren Tahun Ajaran Spesifik

```
GET /api/akademik/santri-analytics/scores-trend?tahun_ajaran=2024-2025
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `tahun_ajaran=2024-2025`

---

### Example 4: Kombinasi Filter (Mapel + Tahun Ajaran)

```
GET /api/akademik/santri-analytics/scores-trend?kode_mapel=MAT001&tahun_ajaran=2024-2025
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `kode_mapel=MAT001`
- `tahun_ajaran=2024-2025`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "kode_mapel": "MAT001",
      "nama_mapel": "Matematika",
      "trend": [
        {
          "semester": 1,
          "tahun_ajaran": "2023-2024",
          "nilai_akhir": 75.5,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "semester": 2,
          "tahun_ajaran": "2023-2024",
          "nilai_akhir": 78,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "semester": 1,
          "tahun_ajaran": "2024-2025",
          "nilai_akhir": 82,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "semester": 2,
          "tahun_ajaran": "2024-2025",
          "nilai_akhir": 86.5,
          "status_ketuntasan": "TUNTAS"
        }
      ]
    },
    {
      "kode_mapel": "IPA001",
      "nama_mapel": "Ilmu Pengetahuan Alam",
      "trend": [
        {
          "semester": 1,
          "tahun_ajaran": "2023-2024",
          "nilai_akhir": 70,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "semester": 2,
          "tahun_ajaran": "2023-2024",
          "nilai_akhir": 72.5,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "semester": 1,
          "tahun_ajaran": "2024-2025",
          "nilai_akhir": 75,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "semester": 2,
          "tahun_ajaran": "2024-2025",
          "nilai_akhir": 79.5,
          "status_ketuntasan": "TUNTAS"
        }
      ]
    }
  ],
  "filters": {
    "kode_mapel": null,
    "tahun_ajaran": null
  }
}
```

### Field Explanation:

| Field | Type | Description |
|-------|------|-------------|
| `kode_mapel` | string | Kode unik mata pelajaran |
| `nama_mapel` | string | Nama mata pelajaran |
| `trend` | array | Array perubahan nilai per semester |
| `semester` | integer | Nomor semester (1 atau 2) |
| `tahun_ajaran` | string | Format tahun ajaran (e.g., 2024-2025) |
| `nilai_akhir` | float | Nilai akhir pada semester tersebut |
| `status_ketuntasan` | string | Status KKM di semester tersebut |

---

## Use Cases

- **Line Chart**: Gunakan `trend` array untuk visualisasi tren nilai per mapel
- **Progress Tracking**: Lihat apakah nilai meningkat, menurun, atau stabil dari semester ke semester
- **Identifikasi Pola**: Kapan mapel tertentu mulai mengalami penurunan/peningkatan

---

## Notes

- Data diurutkan berdasarkan `tahun_ajaran` ASC kemudian `semester` ASC
- Jika santri belum pernah ambil mapel di semester tertentu, mapel itu tidak muncul di periode itu
- Auth required: token santri (DataAkunSantri)
