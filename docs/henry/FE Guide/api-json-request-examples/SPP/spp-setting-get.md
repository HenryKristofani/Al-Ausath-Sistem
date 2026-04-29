# GET /api/administrasi/spp/setting

Auth: `sanctum` (Role: Petugas Admin / Petugas Tata Usaha)

Endpoint ini dipakai untuk mendapatkan daftar pengaturan biaya SPP.

## Contoh request

```json
{
  "query": {
    "per_page": 10,
    "kode_kelas": "MTS-7A",
    "id_golongan_spp": 1
  }
}
```

## Catatan

- Filter bersifat opsional.
