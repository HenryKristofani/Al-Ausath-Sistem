# POST /api/akademik/raport/tarik

Auth: `sanctum`

Endpoint ini dipakai untuk menarik kembali raport yang sudah berstatus `TERBIT` menjadi `DRAFT`.

## Contoh request body

```json
{
  "kode_kelas": "KLS-10A",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "nomor_induk": "2025001"
}
```

## Contoh response sukses

```json
{
  "message": "Status raport berhasil ditarik kembali menjadi DRAFT.",
  "data": {
    "total_terupdate": 1,
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "nomor_induk": "2025001"
  }
}
```

## Contoh response gagal

```json
{
  "message": "Tidak ada raport TERBIT yang bisa ditarik dengan filter yang diberikan.",
  "data": {
    "total_terupdate": 0,
    "kode_kelas": "KLS-10A",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "nomor_induk": "2025001"
  }
}
```

## Catatan

- `nomor_induk` bersifat opsional. Jika tidak dikirim, backend akan menarik semua raport `TERBIT` untuk kelas, tahun ajaran, dan semester yang dipilih.
- Endpoint ini hanya memproses raport yang sudah berstatus `TERBIT`.
- Jika tidak ada data yang cocok, backend mengembalikan status `422`.