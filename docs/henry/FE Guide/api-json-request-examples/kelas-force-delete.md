# DELETE /api/akademik/kelas/{id}/force

Auth: `sanctum`

Endpoint ini dipakai untuk menghapus permanen data kelas dari trash.

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
  "message": "Data kelas berhasil dihapus permanen."
}
```

## Contoh response gagal

```json
{
  "message": "Data kelas tidak dapat dihapus permanen karena masih dipakai data lain.",
  "data": {
    "dependencies": {
      "data_santri": 1,
      "data_kelas_mapel": 0,
      "data_nilai_siswa": 0,
      "data_raport": 0,
      "ppdb_pendaftar": 0,
      "total": 1
    }
  }
}
```

## Catatan

- Gunakan `id_kelas` sebagai path parameter `id`.
- Endpoint ini hanya bekerja untuk data yang sudah ada di trash.
