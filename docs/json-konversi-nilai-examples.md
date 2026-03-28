# JSON Request Examples - Konversi Nilai API
## Endpoint: `/api/akademik/konversi-nilai`

---

## 1️⃣ INDEX (List All) - GET
**Endpoint:** `GET /api/akademik/konversi-nilai`

### Request dengan Filter
```bash
GET /api/akademik/konversi-nilai?per_page=10&kode_unit=MTS&status=AKTIF
```

### Headers
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Query Parameters (Opsional)
```json
{
  "per_page": 10,
  "kode_unit": "MTS",
  "status": "AKTIF"
}
```

### Response 200 OK
```json
{
  "data": [
    {
      "id_konversi": 1,
      "kode_unit": "MTS",
      "nilai_min": 85,
      "nilai_max": 100,
      "nilai_huruf": "A",
      "predikat": "Sangat Baik",
      "status": "AKTIF",
      "created_at": "2025-01-15T10:30:00.000000Z",
      "updated_at": "2025-01-15T10:30:00.000000Z"
    },
    {
      "id_konversi": 2,
      "kode_unit": "MTS",
      "nilai_min": 75,
      "nilai_max": 84,
      "nilai_huruf": "B",
      "predikat": "Baik",
      "status": "AKTIF",
      "created_at": "2025-01-15T10:31:00.000000Z",
      "updated_at": "2025-01-15T10:31:00.000000Z"
    },
    {
      "id_konversi": 3,
      "kode_unit": "MTS",
      "nilai_min": 65,
      "nilai_max": 74,
      "nilai_huruf": "C",
      "predikat": "Cukup",
      "status": "AKTIF",
      "created_at": "2025-01-15T10:32:00.000000Z",
      "updated_at": "2025-01-15T10:32:00.000000Z"
    }
  ],
  "links": {
    "first": "http://localhost:8000/api/akademik/konversi-nilai?page=1",
    "last": "http://localhost:8000/api/akademik/konversi-nilai?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://localhost:8000/api/akademik/konversi-nilai",
    "per_page": 10,
    "to": 3,
    "total": 3
  }
}
```

---

## 2️⃣ SHOW (Detail) - GET
**Endpoint:** `GET /api/akademik/konversi-nilai/{id}`

### Request
```bash
GET /api/akademik/konversi-nilai/1
```

### Headers
```
Authorization: Bearer {token}
Accept: application/json
```

### Response 200 OK
```json
{
  "data": {
    "id_konversi": 1,
    "kode_unit": "MTS",
    "nilai_min": 85,
    "nilai_max": 100,
    "nilai_huruf": "A",
    "predikat": "Sangat Baik",
    "status": "AKTIF",
    "created_at": "2025-01-15T10:30:00.000000Z",
    "updated_at": "2025-01-15T10:30:00.000000Z"
  }
}
```

### Response 404 Not Found
```json
{
  "message": "No query results found for model [App\\Models\\DataKonversiNilai] 999"
}
```

---

## 3️⃣ CREATE (Store) - POST
**Endpoint:** `POST /api/akademik/konversi-nilai`

### Headers
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Request Body - Contoh 1 (Dengan kode_unit)
```json
{
  "kode_unit": "MTS",
  "nilai_min": 85,
  "nilai_max": 100,
  "nilai_huruf": "A",
  "predikat": "Sangat Baik",
  "status": "AKTIF"
}
```

### Request Body - Contoh 2 (Tanpa kode_unit - Global)
```json
{
  "nilai_min": 75,
  "nilai_max": 84,
  "nilai_huruf": "B",
  "predikat": "Baik",
  "status": "AKTIF"
}
```

### Request Body - Contoh 3 (Minimal - Status default AKTIF)
```json
{
  "kode_unit": "SMA",
  "nilai_min": 65,
  "nilai_max": 74,
  "nilai_huruf": "C",
  "predikat": "Cukup"
}
```

### Response 201 Created
```json
{
  "message": "Konversi nilai berhasil dibuat.",
  "data": {
    "kode_unit": "MTS",
    "nilai_min": 85,
    "nilai_max": 100,
    "nilai_huruf": "A",
    "predikat": "Sangat Baik",
    "status": "AKTIF",
    "updated_at": "2025-01-15T11:45:30.000000Z",
    "created_at": "2025-01-15T11:45:30.000000Z",
    "id_konversi": 10
  }
}
```

### Response 422 Unprocessable Entity - Validation Error
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "kode_unit": [
      "The selected kode_unit is invalid."
    ],
    "nilai_min": [
      "The nilai_min field is required."
    ],
    "nilai_max": [
      "The nilai_max field must be greater than or equal to nilai_min."
    ]
  }
}
```

---

## 4️⃣ UPDATE (Edit) - PUT
**Endpoint:** `PUT /api/akademik/konversi-nilai/{id}`

### Headers
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Request Body - Contoh 1 (Update semua field)
```json
{
  "kode_unit": "MTS",
  "nilai_min": 90,
  "nilai_max": 100,
  "nilai_huruf": "A+",
  "predikat": "Sempurna",
  "status": "AKTIF"
}
```

### Request Body - Contoh 2 (Update partial - hanya predikat)
```json
{
  "predikat": "Sangat Baik Sekali"
}
```

### Request Body - Contoh 3 (Update status ke NONAKTIF)
```json
{
  "status": "NONAKTIF"
}
```

### Request Body - Contoh 4 (Update range nilai)
```json
{
  "nilai_min": 88,
  "nilai_max": 99
}
```

### Response 200 OK
```json
{
  "message": "Konversi nilai berhasil diperbarui.",
  "data": {
    "id_konversi": 1,
    "kode_unit": "MTS",
    "nilai_min": 90,
    "nilai_max": 100,
    "nilai_huruf": "A+",
    "predikat": "Sempurna",
    "status": "AKTIF",
    "created_at": "2025-01-15T10:30:00.000000Z",
    "updated_at": "2025-01-15T12:00:45.000000Z"
  }
}
```

### Response 422 Unprocessable Entity - Invalid Range
```json
{
  "message": "nilai_max harus lebih besar atau sama dengan nilai_min."
}
```

---

## 5️⃣ DELETE (Hapus) - DELETE
**Endpoint:** `DELETE /api/akademik/konversi-nilai/{id}`

### Headers
```
Authorization: Bearer {token}
Accept: application/json
```

### Response 200 OK
```json
{
  "message": "Konversi nilai berhasil dihapus."
}
```

### Response 404 Not Found
```json
{
  "message": "No query results found for model [App\\Models\\DataKonversiNilai] 999"
}
```

---

## 📋 Validation Rules

| Field | Rules | Keterangan |
|-------|-------|-----------|
| `kode_unit` | nullable, string, max:10, exists:data_unit,kode_unit | Referensi ke tabel data_unit. Boleh null = global |
| `nilai_min` | required (create), numeric, min:0, max:100 | Nilai minimum range konversi |
| `nilai_max` | required (create), numeric, min:0, max:100, ≥nilai_min | Nilai maximum, harus ≥ nilai_min |
| `nilai_huruf` | required (create), string, max:5 | Huruf nilai (A, B, C, dll) |
| `predikat` | nullable, string, max:50 | Deskripsi predikat (Sangat Baik, dll) |
| `status` | nullable (default AKTIF), in:AKTIF,NONAKTIF | Status aktif/nonaktif |

---

## 🔐 Authentication & Authorization

✅ **Endpoint:** Semua endpoint memerlukan `Authorization: Bearer {token}`  
✅ **Guard:** `auth:sanctum`  
✅ **Role:** Terbuka untuk semua role yang terautentikasi (admin, petugas, guru, santri)  
✅ **Token:** Dapat diperoleh dari endpoint login (guard: petugas atau santri)

---

## 🧪 Test dengan cURL

### Test 1 - List
```bash
curl -X GET "http://localhost:8000/api/akademik/konversi-nilai?per_page=5" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 2 - Create
```bash
curl -X POST "http://localhost:8000/api/akademik/konversi-nilai" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kode_unit": "MTS",
    "nilai_min": 85,
    "nilai_max": 100,
    "nilai_huruf": "A",
    "predikat": "Sangat Baik",
    "status": "AKTIF"
  }'
```

### Test 3 - Show
```bash
curl -X GET "http://localhost:8000/api/akademik/konversi-nilai/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Test 4 - Update
```bash
curl -X PUT "http://localhost:8000/api/akademik/konversi-nilai/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "predikat": "Sempurna"
  }'
```

### Test 5 - Delete
```bash
curl -X DELETE "http://localhost:8000/api/akademik/konversi-nilai/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## ✨ Contoh Use Case Lengkap

### Workflow: Create Grade Conversion untuk MTS
```json
POST /api/akademik/konversi-nilai

{
  "kode_unit": "MTS",
  "nilai_min": 85,
  "nilai_max": 100,
  "nilai_huruf": "A",
  "predikat": "Sangat Baik",
  "status": "AKTIF"
}
```

```json
POST /api/akademik/konversi-nilai

{
  "kode_unit": "MTS",
  "nilai_min": 75,
  "nilai_max": 84,
  "nilai_huruf": "B",
  "predikat": "Baik",
  "status": "AKTIF"
}
```

```json
POST /api/akademik/konversi-nilai

{
  "kode_unit": "MTS",
  "nilai_min": 65,
  "nilai_max": 74,
  "nilai_huruf": "C",
  "predikat": "Cukup",
  "status": "AKTIF"
}
```

### Workflow: View → Update → Nonaktifkan
```bash
# 1. Get detail
GET /api/akademik/konversi-nilai/1

# 2. Update predikat
PUT /api/akademik/konversi-nilai/1
{
  "predikat": "Sempurna Sekali"
}

# 3. Nonaktifkan karena diperbaharui
PUT /api/akademik/konversi-nilai/1
{
  "status": "NONAKTIF"
}
```
