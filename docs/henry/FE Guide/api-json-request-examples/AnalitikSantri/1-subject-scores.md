# 📚 Nilai per Mata Pelajaran Semester Saat Ini

**Endpoint:** `GET /api/akademik/santri-analytics/subject-scores`

**Purpose:** Menampilkan nilai santri yang login per mata pelajaran dengan breakdown Harian, UTS, UAS, dan nilai akhir di semester tertentu.

---

## Request Examples

### Example 1: Ambil Nilai Semester Terbaru (Auto-detect)

```
GET /api/akademik/santri-analytics/subject-scores
Authorization: Bearer {santri_token}
```

**Query Parameters:** (none - otomatis ambil semester tertinggi)

---

### Example 2: Nilai Semester Spesifik

```
GET /api/akademik/santri-analytics/subject-scores?semester=2
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `semester=2` (1 atau 2)

---

### Example 3: Nilai Tahun Ajaran Spesifik

```
GET /api/akademik/santri-analytics/subject-scores?tahun_ajaran=2024-2025&semester=1
Authorization: Bearer {santri_token}
```

**Query Parameters:**

- `tahun_ajaran=2024-2025`
- `semester=1`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "kode_mapel": "MAT001",
      "nama_mapel": "Matematika",
      "nilai_harian": 85,
      "nilai_uts": 80,
      "nilai_uas": 90,
      "nilai_akhir": 86.5,
      "nilai_rapor_tampil": 87,
      "status_ketuntasan": "TUNTAS"
    },
    {
      "kode_mapel": "IPA001",
      "nama_mapel": "Ilmu Pengetahuan Alam",
      "nilai_harian": 78,
      "nilai_uts": 75,
      "nilai_uas": 82,
      "nilai_akhir": 79.5,
      "nilai_rapor_tampil": 80,
      "status_ketuntasan": "TUNTAS"
    },
    {
      "kode_mapel": "IND001",
      "nama_mapel": "Bahasa Indonesia",
      "nilai_harian": 70,
      "nilai_uts": 65,
      "nilai_uas": 68,
      "nilai_akhir": 68,
      "nilai_rapor_tampil": 68,
      "status_ketuntasan": "BELUM TUNTAS"
    }
  ],
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
| `nilai_harian` | float | Nilai kuis/harian |
| `nilai_uts` | float | Nilai ujian tengah semester |
| `nilai_uas` | float | Nilai ujian akhir semester |
| `nilai_akhir` | float | Nilai akhir yang sudah dihitung dengan bobot |
| `nilai_rapor_tampil` | float | Nilai yang ditampilkan di rapor |
| `status_ketuntasan` | string | Status KKM (TUNTAS / BELUM TUNTAS) |

---

## Notes

- Auto-detect semester: Jika `semester` tidak diberikan, endpoint ambil semester tertinggi dari riwayat santri
- Nilai default 0 jika tidak ada data
- Auth required: token santri (DataAkunSantri)
- Multiple mapel: Jika santri ambil banyak mapel, response berisi array untuk semua mapel
