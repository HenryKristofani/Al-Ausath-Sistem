# GET /api/wali-santri/{nomor_induk}

Auth: `sanctum`

Endpoint ini dipakai untuk mengambil data wali/orang tua santri berdasarkan `nomor_induk` secara spesifik.

## Contoh request

```json
{
  "params": {
    "nomor_induk": "12345678"
  }
}
```

## Contoh response

```json
{
  "data": {
    "id_santri": 1,
    "nomor_induk": "12345678",
    "nama_lengkap_santri": "Ahmad Fulan",
    "jenis_kelamin": "L",
    "nama_ayah_kandung": "Budi",
    "nama_ibu_kandung": "Siti",
    "nama_wali": "Hasan",
    "nama_orang_tua_aktif": "Budi"
  }
}
```

## Catatan

- Gunakan nilai `nomor_induk` sebagai path parameter.
- Atribut `nama_orang_tua_aktif` otomatis menyesuaikan jenis kelamin santri:
  - Berisi `nama_ayah_kandung` jika jenis kelamin santri adalah Laki-laki (`L`).
  - Berisi `nama_ibu_kandung` jika jenis kelamin santri adalah Perempuan (`P`).
