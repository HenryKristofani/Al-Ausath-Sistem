# PUT /api/data-master/jadwal-pembelajaran/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk memperbarui jadwal pembelajaran.

## Contoh request body

```json
{
  "hari": "SELASA",
  "jam_mulai": "08:00:00",
  "jam_selesai": "09:30:00",
  "ruangan": "R-2",
  "status": "AKTIF"
}
```

## Contoh response sukses

```json
{
  "message": "Data jadwal pembelajaran berhasil diperbarui.",
  "data": {
    "id_jadwal": 10,
    "id_kelas_mapel": 5,
    "tahun_ajaran": "2024/2025",
    "hari": "SELASA",
    "jam_mulai": "08:00:00",
    "jam_selesai": "09:30:00",
    "ruangan": "R-2",
    "status": "AKTIF",
    "kelas_mapel": {
      "id_kelas_mapel": 5,
      "kode_kelas": "KLS-10A",
      "kode_mapel": "MATH-01",
      "id_petugas": 4
    }
  }
}
```
