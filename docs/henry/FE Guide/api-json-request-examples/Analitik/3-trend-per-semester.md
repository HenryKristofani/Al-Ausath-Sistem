# 📈 Grafik Perkembangan Nilai (Trend per Semester)

**Endpoint:** `GET /api/akademik/nilai-statistik/trend`

**Purpose:** Tracking perkembangan akademik per semester (cocok untuk line chart)

---

## Request Examples

### Example 1: Trend Semua Santri

```
GET /api/akademik/nilai-statistik/trend
Authorization: Bearer {token}
```

**Query Parameters:** (none)

---

### Example 2: Trend per Santri

```
GET /api/akademik/nilai-statistik/trend?nomor_induk=001
Authorization: Bearer {token}
```

**Query Parameters:**

- `nomor_induk=001`

---

### Example 3: Trend per Kelas

```
GET /api/akademik/nilai-statistik/trend?kode_kelas=9-PA
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`

---

### Example 4: Trend per Mata Pelajaran

```
GET /api/akademik/nilai-statistik/trend?kode_mapel=MAPEL-001&tahun_ajaran=2025/2026
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_mapel=MAPEL-001`
- `tahun_ajaran=2025/2026`

---

### Example 5: Trend Santri Spesifik Semua Semester

```
GET /api/akademik/nilai-statistik/trend?nomor_induk=001&tahun_ajaran=2025/2026
Authorization: Bearer {token}
```

**Query Parameters:**

- `nomor_induk=001`
- `tahun_ajaran=2025/2026`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "semester": 1,
      "rata_rata": 74.5,
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
  "filters": {
    "nomor_induk": null,
    "kode_kelas": null,
    "kode_mapel": null,
    "tahun_ajaran": null
  }
}
```

### Field Explanation:

- `semester` - Nomor semester (1 atau 2)
- `rata_rata` - Nilai rata-rata semester (desimal 2 digit)
- `tertinggi` - Nilai tertinggi dalam semester
- `terendah` - Nilai terendah dalam semester
- `jumlah_santri` - Jumlah santri yang memiliki nilai dalam semester

**Note:** Data diurutkan dari semester terendah ke tertinggi

---

## Example Response: Trend Santri Spesifik

```json
{
  "data": [
    {
      "semester": 1,
      "rata_rata": 78.5,
      "tertinggi": 92,
      "terendah": 65,
      "jumlah_santri": 1
    },
    {
      "semester": 2,
      "rata_rata": 80.25,
      "tertinggi": 95,
      "terendah": 70,
      "jumlah_santri": 1
    }
  ],
  "filters": {
    "nomor_induk": "001",
    "kode_kelas": null,
    "kode_mapel": null,
    "tahun_ajaran": "2025/2026"
  }
}
```

---

## Error Responses

### 422 - Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "nomor_induk": ["The nomor induk field must exist in data_santri."]
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
curl -X GET "http://localhost:8000/api/akademik/nilai-statistik/trend?nomor_induk=001&tahun_ajaran=2025/2026" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Frontend Usage

### JavaScript (Fetch) - For Line Chart

```javascript
const fetchTrend = async (filters = {}) => {
  const queryParams = new URLSearchParams(filters).toString();
  const response = await fetch(
    `/api/akademik/nilai-statistik/trend?${queryParams}`,
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
const data = await fetchTrend({
  nomor_induk: "001",
  tahun_ajaran: "2025/2026",
});

// Transform untuk Line Chart
const chartData = {
  labels: data.data.map((d) => `Semester ${d.semester}`),
  datasets: [
    {
      label: "Nilai Rata-rata",
      data: data.data.map((d) => d.rata_rata),
      borderColor: "#36A2EB",
      tension: 0.1,
    },
  ],
};
```

### Vue.js (Axios) - Line Chart Integration

```javascript
import axios from "axios";
import { Line } from "vue-chartjs";

export default {
  extends: Line,
  data() {
    return {
      chartData: null,
      options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
          y: {
            beginAtZero: false,
            min: 0,
            max: 100,
          },
        },
      },
    };
  },
  mounted() {
    this.loadTrendData();
  },
  methods: {
    async loadTrendData() {
      try {
        const { data } = await axios.get(
          "/api/akademik/nilai-statistik/trend",
          {
            params: {
              nomor_induk: this.$route.params.nomor_induk,
              tahun_ajaran: "2025/2026",
            },
            headers: {
              Authorization: `Bearer ${this.$store.state.token}`,
            },
          },
        );

        this.chartData = {
          labels: data.data.map((d) => `Semester ${d.semester}`),
          datasets: [
            {
              label: "Nilai Rata-rata",
              data: data.data.map((d) => d.rata_rata),
              borderColor: "#36A2EB",
              backgroundColor: "rgba(54, 162, 235, 0.1)",
              tension: 0.1,
            },
            {
              label: "Tertinggi",
              data: data.data.map((d) => d.tertinggi),
              borderColor: "#4BC0C0",
              borderDash: [5, 5],
              tension: 0.1,
            },
            {
              label: "Terendah",
              data: data.data.map((d) => d.terendah),
              borderColor: "#FF6384",
              borderDash: [5, 5],
              tension: 0.1,
            },
          ],
        };

        this.render();
      } catch (error) {
        console.error("Error loading trend:", error.response.data);
      }
    },
  },
};
```

### React Example

```javascript
import { useState, useEffect } from "react";
import axios from "axios";
import { Line } from "react-chartjs-2";

const TrendChart = ({ token, nomorInduk }) => {
  const [chartData, setChartData] = useState(null);

  useEffect(() => {
    axios
      .get("/api/akademik/nilai-statistik/trend", {
        params: {
          nomor_induk: nomorInduk,
          tahun_ajaran: "2025/2026",
        },
        headers: { Authorization: `Bearer ${token}` },
      })
      .then((res) => {
        setChartData({
          labels: res.data.data.map((d) => `Semester ${d.semester}`),
          datasets: [
            {
              label: "Rata-rata",
              data: res.data.data.map((d) => d.rata_rata),
              borderColor: "#36A2EB",
              tension: 0.1,
            },
            {
              label: "Tertinggi",
              data: res.data.data.map((d) => d.tertinggi),
              borderColor: "#4BC0C0",
              borderDash: [5, 5],
            },
            {
              label: "Terendah",
              data: res.data.data.map((d) => d.terendah),
              borderColor: "#FF6384",
              borderDash: [5, 5],
            },
          ],
        });
      });
  }, [token, nomorInduk]);

  return chartData ? <Line data={chartData} /> : <p>Loading...</p>;
};

export default TrendChart;
```
