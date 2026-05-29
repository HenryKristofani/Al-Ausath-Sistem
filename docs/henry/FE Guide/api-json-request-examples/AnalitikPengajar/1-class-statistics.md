# 📊 Statistik Nilai Kelas yang Diampu

**Endpoint:** `GET /api/akademik/analytics/class-statistics`

**Purpose:** Menampilkan statistik nilai rata-rata keseluruhan per kelas yang diajar oleh pengajar yang sedang login.

---

## Request Examples

### Example 1: Semua Kelas yang Diajar (Tanpa Filter)

```
GET /api/akademik/analytics/class-statistics
Authorization: Bearer {pengajar_token}
```

**Query Parameters:** (none - menampilkan semua kelas dan mapel yang diampu pengajar)

---

### Example 2: Kelas Spesifik Tahun Ajaran

```
GET /api/akademik/analytics/class-statistics?tahun_ajaran=2024-2025
Authorization: Bearer {pengajar_token}
```

**Query Parameters:**

- `tahun_ajaran=2024-2025`

---

### Example 3: Semester Spesifik

```
GET /api/akademik/analytics/class-statistics?tahun_ajaran=2024-2025&semester=2
Authorization: Bearer {pengajar_token}
```

**Query Parameters:**

- `tahun_ajaran=2024-2025`
- `semester=2` (1 atau 2)

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "kode_kelas": "9-PA",
      "nama_kelas": "9 PA (Peminatan A)",
      "kode_mapel": "MAT001",
      "nama_mapel": "Matematika",
      "rata_rata": 78.5,
      "tertinggi": 98,
      "terendah": 55,
      "jumlah_santri": 32
    },
    {
      "kode_kelas": "9-PA",
      "nama_kelas": "9 PA (Peminatan A)",
      "kode_mapel": "IPA001",
      "nama_mapel": "Ilmu Pengetahuan Alam",
      "rata_rata": 75.2,
      "tertinggi": 92,
      "terendah": 52,
      "jumlah_santri": 32
    },
    {
      "kode_kelas": "9-PB",
      "nama_kelas": "9 PB (Peminatan B)",
      "kode_mapel": "MAT001",
      "nama_mapel": "Matematika",
      "rata_rata": 76.8,
      "tertinggi": 95,
      "terendah": 58,
      "jumlah_santri": 30
    },
    {
      "kode_kelas": "9-PB",
      "nama_kelas": "9 PB (Peminatan B)",
      "kode_mapel": "IPA001",
      "nama_mapel": "Ilmu Pengetahuan Alam",
      "rata_rata": 74.5,
      "tertinggi": 90,
      "terendah": 50,
      "jumlah_santri": 30
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
| `kode_kelas` | string | Kode unik kelas |
| `nama_kelas` | string | Nama kelas lengkap |
| `kode_mapel` | string | Kode unik mata pelajaran |
| `nama_mapel` | string | Nama mata pelajaran |
| `rata_rata` | float | Nilai rata-rata semua santri di kelas-mapel tersebut |
| `tertinggi` | float | Nilai tertinggi di kelas-mapel tersebut |
| `terendah` | float | Nilai terendah di kelas-mapel tersebut |
| `jumlah_santri` | integer | Jumlah santri yang memiliki nilai di kelas-mapel tersebut |

---

## Use Cases

- **Dashboard Overview**: Lihat performa keseluruhan di semua kelas yang diajar
- **Kelas Comparison**: Bandingkan performa antar kelas untuk mapel yang sama
- **Performance Insight**: Identifikasi kelas mana yang perlu perhatian khusus
- **Report Generation**: Data untuk laporan kinerja mengajar per semester

---

## Notes

- Menampilkan kombinasi kode_kelas + kode_mapel (per kelas per mapel yang diajar)
- Jika pengajar mengajar beberapa mapel di satu kelas, masing-masing mapel akan punya row tersendiri
- Auth required: token pengajar (DataPetugas)
- Data difilter dari `DataKelasMapel` berdasarkan `id_petugas` dari token yang login
