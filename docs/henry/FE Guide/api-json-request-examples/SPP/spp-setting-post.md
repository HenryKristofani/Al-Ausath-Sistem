# POST /api/administrasi/spp/setting

Auth: `sanctum` (Role: Petugas Admin / Petugas Tata Usaha)

Endpoint ini dipakai untuk membuat pengaturan biaya SPP baru.

## Contoh request body

```json
{
  "kode_kelas": "MTS-7A",
  "id_golongan_spp": 1,
  "nominal": 250000
}
```

## Catatan

- Mengatur besaran bayaran per kelas berdasarkan golongan.
