# POST /api/akademik/kelas/{id}/restore

Auth: `sanctum`

Endpoint ini dipakai untuk memulihkan data kelas dari trash.

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
  "message": "Data kelas berhasil dipulihkan.",
  "data": {
    "id_kelas": 12,
    "kode_kelas": "X-IPA-1"
  }
}
```

## Contoh response gagal

```json
{
  "message": "Data kelas tidak dapat dipulihkan karena kode_kelas sudah dipakai data aktif lain."
}
```

## Catatan

- Gunakan `id_kelas` sebagai path parameter `id`.
- Restore hanya bisa dilakukan untuk data yang masih ada di trash.
