# GET /api/data-master/jadwal-pembelajaran

Auth: `sanctum`

Endpoint ini dipakai untuk list data jadwal pembelajaran.

## Query params

- `per_page` (int) jumlah data per halaman.
- `id_kelas_mapel` (int) filter berdasarkan kelas-mapel.
- `tahun_ajaran` (string) filter tahun ajaran.
- `hari` (string) filter hari.
- `status` (string) filter status `AKTIF` atau `NONAKTIF`.
- `nomor_induk` (string) filter jadwal berdasarkan nomor induk santri.
- `q` (string) kata kunci (tahun_ajaran, hari, ruangan, jam_mulai, jam_selesai).

## Contoh request

```
GET /api/data-master/jadwal-pembelajaran?per_page=10&tahun_ajaran=2024/2025&hari=SENIN
```

## Contoh response sukses

```json
{
  "current_page": 1,
  "data": [
    {
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
  ],
  "first_page_url": "http://localhost/api/data-master/jadwal-pembelajaran?page=1",
  "from": 1,
  "last_page": 1,
  "last_page_url": "http://localhost/api/data-master/jadwal-pembelajaran?page=1",
  "next_page_url": null,
  "path": "http://localhost/api/data-master/jadwal-pembelajaran",
  "per_page": 10,
  "prev_page_url": null,
  "to": 1,
  "total": 1
}
```
