# 🏆 Identifikasi Santri Berprestasi

**Endpoint:** `GET /api/akademik/nilai-statistik/berprestasi`

**Purpose:** Mengidentifikasi santri dengan performa tinggi (top performers)

---

## Request Examples

### Example 1: Top Performers Global (Default: Threshold 85, Limit 10)

```
GET /api/akademik/nilai-statistik/berprestasi
Authorization: Bearer {token}
```

**Query Parameters:** (none - menggunakan default)

---

### Example 2: Top Performers per Kelas

```
GET /api/akademik/nilai-statistik/berprestasi?kode_kelas=9-PA&limit=5
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`
- `limit=5`

---

### Example 3: Top Performers dengan Custom Threshold

```
GET /api/akademik/nilai-statistik/berprestasi?threshold=80&limit=10
Authorization: Bearer {token}
```

**Query Parameters:**

- `threshold=80` (nilai minimum untuk dianggap berprestasi)
- `limit=10`

---

### Example 4: Top Performers per Tahun & Semester

```
GET /api/akademik/nilai-statistik/berprestasi?tahun_ajaran=2025/2026&semester=1&limit=10
Authorization: Bearer {token}
```

**Query Parameters:**

- `tahun_ajaran=2025/2026`
- `semester=1`
- `limit=10`

---

### Example 5: Kombinasi Filter Lengkap

```
GET /api/akademik/nilai-statistik/berprestasi?kode_kelas=9-PA&tahun_ajaran=2025/2026&semester=1&threshold=85&limit=10
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`
- `tahun_ajaran=2025/2026`
- `semester=1`
- `threshold=85`
- `limit=10`

---

## Response Format

### Success Response (200 OK)

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
        {
          "kode_mapel": "MAPEL-002",
          "nilai_akhir": 87,
          "nilai_tampil": 87,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "kode_mapel": "MAPEL-003",
          "nilai_akhir": 92,
          "nilai_tampil": 92,
          "status_ketuntasan": "TUNTAS"
        }
      ]
    },
    {
      "nomor_induk": "003",
      "rata_rata": 86.5,
      "mapel_count": 8,
      "nilai_detail": [
        {
          "kode_mapel": "MAPEL-001",
          "nilai_akhir": 88,
          "nilai_tampil": 88,
          "status_ketuntasan": "TUNTAS"
        },
        {
          "kode_mapel": "MAPEL-002",
          "nilai_akhir": 85,
          "nilai_tampil": 85,
          "status_ketuntasan": "TUNTAS"
        }
      ]
    }
  ],
  "count": 2,
  "filters": {
    "threshold": 85,
    "limit": 10,
    "kode_kelas": "9-PA",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

### Field Explanation:

- `nomor_induk` - Nomor induk santri
- `rata_rata` - Nilai rata-rata santri (desimal 2 digit)
- `mapel_count` - Jumlah mata pelajaran yang diambil
- `nilai_detail` - Array detail nilai per mapel
- `count` - Jumlah santri yang memenuhi kriteria

**Note:** Data diurutkan dari `rata_rata` tertinggi ke terendah (descending)

---

## Parameter Details

| Parameter      | Type    | Default | Description                              |
| -------------- | ------- | ------- | ---------------------------------------- |
| `kode_kelas`   | string  | null    | Filter by kelas (optional)               |
| `tahun_ajaran` | string  | null    | Filter by tahun ajaran (optional)        |
| `semester`     | integer | null    | Filter by semester 1 atau 2 (optional)   |
| `threshold`    | numeric | 85      | Nilai minimum untuk dianggap berprestasi |
| `limit`        | integer | 10      | Jumlah top performers (max: 100)         |

---

## Error Responses

### 422 - Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "limit": ["The limit field must be max 100."]
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
curl -X GET "http://localhost:8000/api/akademik/nilai-statistik/berprestasi?kode_kelas=9-PA&threshold=85&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Frontend Usage

### JavaScript (Fetch)

```javascript
const fetchTopPerformers = async (filters = {}) => {
  const defaultFilters = {
    threshold: 85,
    limit: 10,
    ...filters,
  };

  const queryParams = new URLSearchParams(defaultFilters).toString();
  const response = await fetch(
    `/api/akademik/nilai-statistik/berprestasi?${queryParams}`,
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
const data = await fetchTopPerformers({
  kode_kelas: "9-PA",
  semester: 1,
  limit: 5,
});

// Display in table
data.data.forEach((santri) => {
  console.log(`${santri.nomor_induk} - Rata-rata: ${santri.rata_rata}`);
  santri.nilai_detail.forEach((mapel) => {
    console.log(`  ${mapel.kode_mapel}: ${mapel.nilai_akhir}`);
  });
});
```

### Vue.js (Axios) - Table Component

```javascript
import axios from "axios";

export default {
  data() {
    return {
      topPerformers: [],
      loading: false,
      filters: {
        threshold: 85,
        limit: 10,
        kode_kelas: "9-PA",
        semester: 1,
      },
    };
  },
  mounted() {
    this.loadTopPerformers();
  },
  methods: {
    async loadTopPerformers() {
      this.loading = true;
      try {
        const { data } = await axios.get(
          "/api/akademik/nilai-statistik/berprestasi",
          {
            params: this.filters,
            headers: {
              Authorization: `Bearer ${this.$store.state.token}`,
            },
          },
        );

        this.topPerformers = data.data;
      } catch (error) {
        console.error("Error loading top performers:", error.response.data);
        this.$toast.error("Gagal mengambil data top performers");
      } finally {
        this.loading = false;
      }
    },
  },
  template: `
    <div>
      <h3>Top Performers (Threshold: {{ filters.threshold }})</h3>
      <table v-if="!loading" class="table">
        <thead>
          <tr>
            <th>Nomor Induk</th>
            <th>Rata-rata</th>
            <th>Mapel</th>
            <th>Detail</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="santri in topPerformers" :key="santri.nomor_induk">
            <td>{{ santri.nomor_induk }}</td>
            <td><strong>{{ santri.rata_rata }}</strong></td>
            <td>{{ santri.mapel_count }}</td>
            <td>
              <button @click="showDetail(santri)">Lihat</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>Loading...</p>
    </div>
  `,
};
```

### React Component

```javascript
import { useState, useEffect } from "react";
import axios from "axios";

const TopPerformersTable = ({ token }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    axios
      .get("/api/akademik/nilai-statistik/berprestasi", {
        params: {
          threshold: 85,
          limit: 10,
          kode_kelas: "9-PA",
          semester: 1,
        },
        headers: { Authorization: `Bearer ${token}` },
      })
      .then((res) => {
        setData(res.data.data);
        setLoading(false);
      })
      .catch((err) => {
        console.error("Error:", err.response.data);
        setLoading(false);
      });
  }, [token]);

  if (loading) return <p>Loading...</p>;

  return (
    <table>
      <thead>
        <tr>
          <th>Nomor Induk</th>
          <th>Rata-rata</th>
          <th>Mapel</th>
        </tr>
      </thead>
      <tbody>
        {data.map((santri) => (
          <tr key={santri.nomor_induk}>
            <td>{santri.nomor_induk}</td>
            <td>
              <strong>{santri.rata_rata}</strong>
            </td>
            <td>{santri.mapel_count}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
};

export default TopPerformersTable;
```
