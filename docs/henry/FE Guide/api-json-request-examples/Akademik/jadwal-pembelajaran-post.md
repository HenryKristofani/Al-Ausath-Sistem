# POST /api/data-master/jadwal-pembelajaran

Auth: `sanctum`

Endpoint ini dipakai untuk membuat jadwal pembelajaran baru.

## Contoh request body

```json
{
  "id_kelas_mapel": 5,
  "tahun_ajaran": "2024/2025",
  "hari": "SENIN",
  "jam_mulai": "07:00:00",
  "jam_selesai": "08:30:00",
  "ruangan": "R-1",
  "status": "AKTIF"
}
```

## Contoh response sukses

```json
{
  "message": "Data jadwal pembelajaran berhasil dibuat.",
  "data": {
    "id_jadwal": 10,
    "id_kelas_mapel": 5,
    "tahun_ajaran": "2024/2025",
    "hari": "SENIN",
    "jam_mulai": "07:00:00",
    "jam_selesai": "08:30:00",
    "ruangan": "R-1",
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
