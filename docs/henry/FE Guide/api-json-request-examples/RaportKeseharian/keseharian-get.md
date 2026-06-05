# GET /api/akademik/raport/keseharian

Auth: `sanctum`

Endpoint ini dipakai untuk mengambil data keseharian rapor per santri-semester (kebersihan, kerapian, keterampilan, kelakuan, kerajinan, kedisiplinan, ketaatan).

## Contoh request

```json
{
  "query": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1
  }
}
```

## Contoh response

```json
{
  "data": {
    "nomor_induk": "2025001",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "kebersihan": "A",
    "kerapian": "B",
    "keterampilan": "A",
    "kelakuan": "B",
    "kerajinan": "A",
    "kedisiplinan": "B",
    "ketaatan": "A"
  }
}
```

## Catatan

- `nomor_induk`, `tahun_ajaran`, dan `semester` wajib diisi.
- Jika data raport belum ada, seluruh field keseharian akan bernilai `null`.
- Response berisi 7 aspek keseharian: `kebersihan`, `kerapian`, `keterampilan`, `kelakuan`, `kerajinan`, `kedisiplinan`, `ketaatan`.
