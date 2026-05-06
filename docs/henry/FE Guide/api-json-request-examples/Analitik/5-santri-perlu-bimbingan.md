# 🆘 Identifikasi Santri Perlu Bimbingan

**Endpoint:** `GET /api/akademik/nilai-statistik/perlu-bimbingan`

**Purpose:** Mengidentifikasi santri yang memerlukan perhatian khusus (nilai < KKM atau rendah)

---

## Request Examples

### Example 1: Santri Perlu Bimbingan Global (Default: Threshold 65, Limit 50)

```
GET /api/akademik/nilai-statistik/perlu-bimbingan
Authorization: Bearer {token}
```

**Query Parameters:** (none - menggunakan default)

---

### Example 2: Santri Perlu Bimbingan per Kelas

```
GET /api/akademik/nilai-statistik/perlu-bimbingan?kode_kelas=9-PA
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`

---

### Example 3: Santri Perlu Bimbingan dengan Custom Threshold

```
GET /api/akademik/nilai-statistik/perlu-bimbingan?threshold=70&limit=20
Authorization: Bearer {token}
```

**Query Parameters:**

- `threshold=70` (nilai maksimum untuk dianggap perlu bimbingan)
- `limit=20`

---

### Example 4: Santri Perlu Bimbingan per Semester

```
GET /api/akademik/nilai-statistik/perlu-bimbingan?tahun_ajaran=2025/2026&semester=1&limit=30
Authorization: Bearer {token}
```

**Query Parameters:**

- `tahun_ajaran=2025/2026`
- `semester=1`
- `limit=30`

---

### Example 5: Kombinasi Filter Lengkap

```
GET /api/akademik/nilai-statistik/perlu-bimbingan?kode_kelas=9-PA&tahun_ajaran=2025/2026&semester=1&threshold=65&limit=50
Authorization: Bearer {token}
```

**Query Parameters:**

- `kode_kelas=9-PA`
- `tahun_ajaran=2025/2026`
- `semester=1`
- `threshold=65`
- `limit=50`

---

## Response Format

### Success Response (200 OK)

```json
{
  "data": [
    {
      "nomor_induk": "015",
      "rata_rata": 58.5,
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
        {
          "kode_mapel": "MAPEL-005",
          "nilai_akhir": 52,
          "nilai_tampil": 52,
          "status_ketuntasan": "BELUM TUNTAS",
          "flag_warna": "HITAM"
        },
        {
          "kode_mapel": "MAPEL-003",
          "nilai_akhir": 60,
          "nilai_tampil": 60,
          "status_ketuntasan": "TUNTAS",
          "flag_warna": "HITAM"
        },
        {
          "kode_mapel": "MAPEL-004",
          "nilai_akhir": 63,
          "nilai_tampil": 63,
          "status_ketuntasan": "TUNTAS",
          "flag_warna": "HITAM"
        },
        {
          "kode_mapel": "MAPEL-001",
          "nilai_akhir": 64,
          "nilai_tampil": 64,
          "status_ketuntasan": "TUNTAS",
          "flag_warna": "HITAM"
        }
      ]
    },
    {
      "nomor_induk": "018",
      "rata_rata": 62.3,
      "mapel_perlu_bimbingan": 6,
      "mapel_belum_tuntas": 2,
      "mapel_detail": [
        {
          "kode_mapel": "MAPEL-001",
          "nilai_akhir": 55,
          "nilai_tampil": 55,
          "status_ketuntasan": "BELUM TUNTAS",
          "flag_warna": "HITAM"
        },
        {
          "kode_mapel": "MAPEL-004",
          "nilai_akhir": 58,
          "nilai_tampil": 58,
          "status_ketuntasan": "BELUM TUNTAS",
          "flag_warna": "HITAM"
        }
      ]
    }
  ],
  "count": 2,
  "filters": {
    "threshold": 65,
    "limit": 50,
    "kode_kelas": "9-PA",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

### Field Explanation:

- `nomor_induk` - Nomor induk santri
- `rata_rata` - Nilai rata-rata santri (desimal 2 digit)
- `mapel_perlu_bimbingan` - Jumlah mapel dengan nilai < threshold atau BELUM TUNTAS
- `mapel_belum_tuntas` - Jumlah mapel dengan status BELUM TUNTAS (nilai < KKM)
- `mapel_detail` - Array detail nilai per mapel yang bermasalah
- `flag_warna` - MERAH (nilai < 50) atau HITAM (nilai ≥ 50)
- `count` - Jumlah santri yang memenuhi kriteria

**Note:** Data diurutkan berdasarkan:

1. `mapel_belum_tuntas` (DESC) - prioritas santri dengan banyak mapel belum tuntas
2. `rata_rata_nilai` (ASC) - nilai rata-rata terendah

---

## Parameter Details

| Parameter      | Type    | Default | Description                                   |
| -------------- | ------- | ------- | --------------------------------------------- |
| `kode_kelas`   | string  | null    | Filter by kelas (optional)                    |
| `tahun_ajaran` | string  | null    | Filter by tahun ajaran (optional)             |
| `semester`     | integer | null    | Filter by semester 1 atau 2 (optional)        |
| `threshold`    | numeric | 65      | Nilai maksimum untuk dianggap perlu bimbingan |
| `limit`        | integer | 50      | Jumlah santri yang ditampilkan (max: 500)     |

---

## Kriteria Inklusi

Santri termasuk dalam list "perlu bimbingan" jika memenuhi SALAH SATU:

1. **Status Ketuntasan:** `status_ketuntasan = 'BELUM TUNTAS'` (nilai < KKM)
2. **Nilai Rendah:** `nilai_akhir_mapel < threshold` (default: 65)

---

## Error Responses

### 422 - Validation Error

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "limit": ["The limit field must be max 500."]
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
curl -X GET "http://localhost:8000/api/akademik/nilai-statistik/perlu-bimbingan?kode_kelas=9-PA&threshold=65&limit=30" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

---

## Frontend Usage

### JavaScript (Fetch)

```javascript
const fetchNeedsHelp = async (filters = {}) => {
  const defaultFilters = {
    threshold: 65,
    limit: 50,
    ...filters,
  };

  const queryParams = new URLSearchParams(defaultFilters).toString();
  const response = await fetch(
    `/api/akademik/nilai-statistik/perlu-bimbingan?${queryParams}`,
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
const data = await fetchNeedsHelp({
  kode_kelas: "9-PA",
  semester: 1,
  threshold: 65,
});

// Display in table
data.data.forEach((santri) => {
  console.log(
    `${santri.nomor_induk} - Rata-rata: ${santri.rata_rata}, Belum Tuntas: ${santri.mapel_belum_tuntas}`,
  );
  santri.mapel_detail.forEach((mapel) => {
    console.log(
      `  ${mapel.kode_mapel}: ${mapel.nilai_akhir} (${mapel.status_ketuntasan})`,
    );
  });
});
```

### Vue.js (Axios) - Priority Table Component

```javascript
import axios from "axios";

export default {
  data() {
    return {
      needsHelpList: [],
      loading: false,
      filters: {
        threshold: 65,
        limit: 50,
        kode_kelas: "9-PA",
        semester: 1,
      },
    };
  },
  computed: {
    sortedByPriority() {
      // Sort by number of belum_tuntas, then by rata_rata
      return this.needsHelpList.sort((a, b) => {
        if (a.mapel_belum_tuntas !== b.mapel_belum_tuntas) {
          return b.mapel_belum_tuntas - a.mapel_belum_tuntas;
        }
        return a.rata_rata - b.rata_rata;
      });
    },
  },
  mounted() {
    this.loadNeedsHelpData();
  },
  methods: {
    async loadNeedsHelpData() {
      this.loading = true;
      try {
        const { data } = await axios.get(
          "/api/akademik/nilai-statistik/perlu-bimbingan",
          {
            params: this.filters,
            headers: {
              Authorization: `Bearer ${this.$store.state.token}`,
            },
          },
        );

        this.needsHelpList = data.data;
      } catch (error) {
        console.error("Error loading data:", error.response.data);
        this.$toast.error("Gagal mengambil data santri perlu bimbingan");
      } finally {
        this.loading = false;
      }
    },
    getRowClass(santri) {
      // Warning class jika > 2 mapel belum tuntas
      return santri.mapel_belum_tuntas > 2 ? "table-danger" : "table-warning";
    },
  },
  template: `
    <div>
      <h3>Santri Perlu Bimbingan (Threshold: {{ filters.threshold }})</h3>
      <table v-if="!loading" class="table">
        <thead>
          <tr>
            <th>Nomor Induk</th>
            <th>Rata-rata</th>
            <th>Belum Tuntas</th>
            <th>Total Mapel</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="santri in sortedByPriority" :key="santri.nomor_induk" :class="getRowClass(santri)">
            <td>{{ santri.nomor_induk }}</td>
            <td><strong>{{ santri.rata_rata }}</strong></td>
            <td><span class="badge badge-danger">{{ santri.mapel_belum_tuntas }}</span></td>
            <td>{{ santri.mapel_perlu_bimbingan }}</td>
            <td>
              <button @click="showDetail(santri)" class="btn btn-sm btn-info">Detail</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>Loading...</p>
    </div>
  `,
};
```

### React Component - Alert Card

```javascript
import { useState, useEffect } from "react";
import axios from "axios";

const NeedsHelpAlert = ({ token, classCode }) => {
  const [data, setData] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    axios
      .get("/api/akademik/nilai-statistik/perlu-bimbingan", {
        params: {
          threshold: 65,
          limit: 50,
          kode_kelas: classCode,
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
  }, [token, classCode]);

  if (loading) return <p>Loading...</p>;

  return (
    <div className="alert-container">
      <h4>Santri Perlu Perhatian: {data.length}</h4>
      {data.map((santri) => (
        <div key={santri.nomor_induk} className="card mb-2">
          <div className="card-body">
            <h6>
              {santri.nomor_induk} - Rata-rata: {santri.rata_rata}
            </h6>
            <p className="text-danger">
              ⚠️ {santri.mapel_belum_tuntas} mapel belum tuntas
            </p>
            <small>
              {santri.mapel_detail
                .filter((m) => m.status_ketuntasan === "BELUM TUNTAS")
                .map((m) => m.kode_mapel)
                .join(", ")}
            </small>
          </div>
        </div>
      ))}
    </div>
  );
};

export default NeedsHelpAlert;
```
