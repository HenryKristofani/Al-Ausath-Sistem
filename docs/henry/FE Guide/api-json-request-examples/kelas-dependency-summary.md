# GET /api/akademik/kelas/{id}/dependency-summary

Auth: `sanctum`

Endpoint ini dipakai untuk melihat ringkasan ketergantungan data kelas sebelum delete permanen.

## Contoh request

```json
{
  "path": {
    "id": 12
  }
}
```

## Contoh response sukses

```json
{
  "data": {
    "id_kelas": 12,
    "kode_kelas": "X-IPA-1",
    "is_deleted": true,
    "dependencies": {
      "data_santri": 0,
      "data_kelas_mapel": 0,
      "data_nilai_siswa": 0,
      "data_raport": 0,
      "ppdb_pendaftar": 0,
      "total": 0
    },
    "can_force_delete": true
  }
}
```

## Catatan

- Gunakan `id_kelas` sebagai path parameter `id`.
- Response membantu FE menentukan apakah tombol hapus permanen bisa diaktifkan.