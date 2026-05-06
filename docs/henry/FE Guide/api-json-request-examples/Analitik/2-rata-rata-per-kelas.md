# 📊 Rata-rata Nilai per Kelas

**Endpoint:** `GET /api/akademik/nilai-statistik/per-kelas`

**Purpose:** Melihat performa tiap kelas untuk bar chart / tabel perbandingan

---

## Request Examples

### Example 1: Rata-rata per Kelas (Semua)

```
GET /api/akademik/nilai-statistik/per-kelas
Authorization: Bearer {token}
```

**Query Parameters:** (none)

---

### Example 2: Rata-rata per Kelas + Tahun Ajaran

```
GET /api/akademik/nilai-statistik/per-kelas?tahun_ajaran=2025/2026
Authorization: Bearer {token}
```

**Query Parameters:**

- `tahun_ajaran=2025/2026`

---

### Example 3: Rata-rata per Kelas + Semester

```
GET /api/akademik/nilai-statistik/per-kelas?semester=1
Authorization: Bearer {token}
```

**Query Parameters:**

- `semester=1`

---

### Example 4: Rata-rata per Kelas + Mata Pelajaran Spesifik

```
GET /api/akademik/nilai-statistik/per-kelas?kode_mapel=MAPEL-001&tahun_ajaran=2025/2026&semester=1
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_mapel=MAPEL-001`
- `tahun_ajaran=2025/2026`
- `semester=1`

---

### Example 5: Kombinasi Filter Lengkap

```
GET /api/akademik/nilai-statistik/per-kelas?tahun_ajaran=2025/2026&semester=1&kode_mapel=MAPEL-002
Authorization: Bearer {token}
```

**Query Parameters:**

- `tahun_ajaran=2025/2026`
- `semester=1`
- `kode_mapel=MAPEL-002`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "kode_kelas": "9-PA",
      "nama_kelas": "Kelas 9 PAI",
      "rata_rata": 78.25,
      "jumlah_santri": 28
    },
    {
      "kode_kelas": "9-PB",
      "nama_kelas": "Kelas 9 Humaniora",
      "rata_rata": 75.8,
      "jumlah_santri": 30
    },
    {
      "kode_kelas": "10-PA",
      "nama_kelas": "Kelas 10 PAI",
      "rata_rata": 76.5,
      "jumlah_santri": 27
    }
  ],
  "filters": {
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "kode_mapel": null
  }
}
```

### Field Explanation:

- `kode_kelas` - Kode kelas
- `nama_kelas` - Nama kelas
- `rata_rata` - Nilai rata-rata kelas (desimal 2 digit)
- `jumlah_santri` - Jumlah santri dalam kelas yang memiliki nilai

**Note:** Data diurutkan dari `rata_rata` tertinggi ke terendah (descending order)

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
curl -X GET "http://localhost:8000/api/akademik/nilai-statistik/per-kelas?tahun_ajaran=2025/2026&semester=1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Frontend Usage

### JavaScript (Fetch)

```javascript
const fetchRataRataPerKelas = async (filters = {}) => {
  const queryParams = new URLSearchParams(filters).toString();
  const response = await fetch(
    `/api/akademik/nilai-statistik/per-kelas?${queryParams}`,
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
const data = await fetchRataRataPerKelas({
  tahun_ajaran: "2025/2026",
  semester: 1,
});

// For Bar Chart
console.log(
  data.data.map((k) => ({
    label: k.nama_kelas,
    value: k.rata_rata,
    santri: k.jumlah_santri,
  })),
);
```

### Vue.js (Axios) - For Bar Chart

```javascript
import axios from "axios";

const getChartData = async (filters) => {
  try {
    const { data } = await axios.get(
      "/api/akademik/nilai-statistik/per-kelas",
      {
        params: filters,
        headers: {
          Authorization: `Bearer ${token}`,
        },
      },
    );

    // Transform for Chart.js or other chart library
    return {
      labels: data.data.map((k) => k.nama_kelas),
      datasets: [
        {
          label: "Rata-rata Nilai",
          data: data.data.map((k) => k.rata_rata),
          backgroundColor: [
            "#FF6384",
            "#36A2EB",
            "#FFCE56",
            "#4BC0C0",
            "#9966FF",
          ],
        },
      ],
    };
  } catch (error) {
    console.error("Error:", error.response.data);
  }
};

// Usage in Vue Component
export default {
  data() {
    return { chartData: null };
  },
  mounted() {
    getChartData({
      tahun_ajaran: "2025/2026",
      semester: 1,
    }).then((data) => {
      this.chartData = data;
    });
  },
};
```

### React Example

```javascript
import { useState, useEffect } from "react";
import axios from "axios";
import { Bar } from "react-chartjs-2";

const KelasPerfChart = ({ token }) => {
  const [chartData, setChartData] = useState(null);

  useEffect(() => {
    axios
      .get("/api/akademik/nilai-statistik/per-kelas", {
        params: {
          tahun_ajaran: "2025/2026",
          semester: 1,
        },
        headers: { Authorization: `Bearer ${token}` },
      })
      .then((res) => {
        setChartData({
          labels: res.data.data.map((k) => k.nama_kelas),
          datasets: [
            {
              label: "Rata-rata Nilai",
              data: res.data.data.map((k) => k.rata_rata),
              backgroundColor: "#36A2EB",
            },
          ],
        });
      });
  }, [token]);

  return chartData ? <Bar data={chartData} /> : <p>Loading...</p>;
};

export default KelasPerfChart;
```
