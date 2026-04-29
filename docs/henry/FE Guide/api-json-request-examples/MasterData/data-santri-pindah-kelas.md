# POST /api/master/data-santri/pindah-kelas

Auth: `sanctum`

Endpoint ini dipakai untuk memindahkan santri ke kelas lain secara massal.

## Contoh request body

```json
{
  "ids": [1, 2, 3],
  "kode_kelas": "X-IPA-2"
}
```

## Catatan

- `ids` adalah array dari `id_santri` yang akan dipindahkan.
- `ids` minimal harus berisi 1 santri.
- `kode_kelas` wajib ada pada tabel `data_kelas`.
- Semua santri dalam array `ids` akan dipindahkan ke kelas yang sama.
- Response akan mengembalikan total santri yang berhasil dipindahkan.
