# POST /api/administrasi/ppdb/pendaftar/{id}/berkas

Auth: `sanctum` (Role: Petugas Admin / Petugas PPDB)

Endpoint ini dipakai untuk mengunggah berkas pendaftaran (Akta, KK, dll).

## Contoh request (FormData)

```json
{
  "tipe_berkas": "akta_kelahiran",
  "file": "<File Object>"
}
```

## Catatan

- Request harus berupa `multipart/form-data`.
- `tipe_berkas` disesuaikan dengan jenis dokumen (misal: `akta_kelahiran`, `kartu_keluarga`, dll).
