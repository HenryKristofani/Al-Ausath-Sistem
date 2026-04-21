# POST /api/akademik/kelas/import

Auth: `sanctum`

Endpoint ini dipakai untuk import data kelas dari file CSV, TXT, XLSX, atau XLS.

## Contoh request

```text
Content-Type: multipart/form-data

file: <CSV/XLSX/XLS file>
```

## Contoh response sukses

```json
{
  "message": "Import data kelas selesai.",
  "data": {
    "inserted": 2,
    "updated": 1,
    "failed": 0,
    "error_rows": [],
    "affected_kelas": [
      {
        "id_kelas": 12,
        "kode_unit": "MA",
        "kode_kelas": "X-IPA-1",
        "nama_kelas": "X IPA 1",
        "nama_jurusan": "IPA",
        "tahun_ajaran": "2025/2026",
        "status": "AKTIF",
        "status_ppdb": "AKTIF"
      }
    ]
  }
}
```

## Catatan

- Gunakan key form-data bernama `file`.
- File CSV/TXT dibaca baris per baris, sedangkan XLSX/XLS diproses lewat import Excel.
- Import memakai strategi upsert berdasarkan `kode_kelas`.
- Response berisi ringkasan `inserted`, `updated`, `failed`, `error_rows`, dan `affected_kelas`.
