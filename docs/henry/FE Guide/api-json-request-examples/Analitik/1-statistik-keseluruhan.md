# 📊 Statistik Nilai Santri (Keseluruhan)

**Endpoint:** `GET /api/akademik/nilai-statistik/`

**Purpose:** Menampilkan gambaran umum performa nilai santri (min, max, avg, count)

---

## Request Examples

### Example 1: Statistik Semua Santri (Global)

```
GET /api/akademik/nilai-statistik/
Authorization: Bearer {token}
```

**Query Parameters:** (none)

---

### Example 2: Statistik per Kelas

```
GET /api/akademik/nilai-statistik/?kode_kelas=9-PA
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`

---

### Example 3: Statistik per Mata Pelajaran

```
GET /api/akademik/nilai-statistik/?kode_mapel=MAPEL-001
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_mapel=MAPEL-001`

---

### Example 4: Statistik per Tahun Ajaran & Semester

```
GET /api/akademik/nilai-statistik/?tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token}
```

**Query Parameters:**

- `tahun_ajaran=2025/2026`
- `semester=1`

---

### Example 5: Kombinasi Filter (Kelas + Tahun + Semester)

```
GET /api/akademik/nilai-statistik/?kode_kelas=9-PA&tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`
- `tahun_ajaran=2025/2026`
- `semester=1`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": {
    "rata_rata": 75.5,
    "nilai_tertinggi": 98,
    "nilai_terendah": 45,
    "jumlah_santri": 32,
    "total_nilai": 256
  },
  "filters": {
    "kode_kelas": "9-PA",
    "kode_mapel": null,
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

### Field Explanation:

- `rata_rata` - Nilai rata-rata dari semua nilai mapel (desimal 2 digit)
- `nilai_tertinggi` - Nilai akhir mapel tertinggi
- `nilai_terendah` - Nilai akhir mapel terendah
- `jumlah_santri` - Jumlah unique santri yang memiliki nilai
- `total_nilai` - Jumlah total records nilai (santri × mapel)

---

## Error Responses

### 422 - Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "semester": ["The semester field must be in:1,2."]
  }
}
```

### 401 - Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

---

## cURL Example

```bash
curl -X GET "http://localhost:8000/api/akademik/nilai-statistik/?tahun_ajaran=2025/2026&semester=1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Frontend Usage

### JavaScript (Fetch)

```javascript
const fetchStatistik = async (filters = {}) => {
  const queryParams = new URLSearchParams(filters).toString();
  const response = await fetch(
    `/api/akademik/nilai-statistik/?${queryParams}`,
    {
      method: "GET",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    },
  );
  return await response.json();
};

// Usage
const data = await fetchStatistik({
  kode_kelas: "9-PA",
  tahun_ajaran: "2025/2026",
  semester: 1,
});

console.log(data.data.rata_rata); // 75.50
```

### Vue.js (Axios)

```javascript
import axios from "axios";

const getStatistik = async (filters) => {
  try {
    const { data } = await axios.get("/api/akademik/nilai-statistik/", {
      params: filters,
      headers: {
        Authorization: `Bearer ${token}`,
      },
    });
    return data;
  } catch (error) {
    console.error("Error:", error.response.data);
  }
};

// Usage
getStatistik({
  kode_kelas: "9-PA",
  tahun_ajaran: "2025/2026",
  semester: 1,
});
```
