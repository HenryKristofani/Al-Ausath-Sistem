# GET /api/akademik/nilai-akhlak/bar

Auth: `sanctum`

Endpoint ini dipakai untuk list semua nilai akhlak tanpa filter nomor induk (cocok untuk dashboard/laporan bulanan).

## Contoh request

```json
{
  "query": {
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "aspek": "AKHLAK",
    "per_page": 20
  }
}
```

## Catatan

- `nomor_induk` tidak diperlukan.
- Semua filter (`tahun_ajaran`, `semester`, `aspek`) bersifat opsional.
- Data yang kembali sudah menyertakan relasi santri dan petugas.
