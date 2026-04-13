# GET /api/master/data-santri/export

Auth: `sanctum`

Endpoint ini dipakai untuk export data santri ke file CSV.

## Contoh request

```json
{
  "query": {}
}
```

## Catatan

- Response akan mengembalikan file CSV yang dapat didownload.
- Semua data santri akan diexport (tidak ada filter).
- Format file adalah CSV dengan semua kolom dari tabel `data_santri`.
- Dapat digunakan untuk backup data atau import ke aplikasi lain.
