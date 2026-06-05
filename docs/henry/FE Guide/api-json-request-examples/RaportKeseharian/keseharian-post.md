# POST /api/akademik/raport/keseharian

Auth: `sanctum`

Endpoint ini dipakai untuk insert atau update seluruh aspek keseharian santri pada rapor (menggunakan mekanisme `updateOrCreate`).

## Contoh request body

```json
{
  "nomor_induk": "2025001",
  "kode_kelas": "KLS01",
  "tahun_ajaran": "2025/2026",
  "semester": 1,
  "kebersihan": "A",
  "kerapian": "B",
  "keterampilan": "A",
  "kelakuan": "B",
  "kerajinan": "A",
  "kedisiplinan": "B",
  "ketaatan": "A",
  "id_wali_kelas": 12
}
```

## Contoh response

```json
{
  "message": "Keseharian raport berhasil disimpan.",
  "data": {
    "id_raport": 35,
    "nomor_induk": "2025001",
    "kode_kelas": "KLS01",
    "tahun_ajaran": "2025/2026",
    "semester": 1,
    "keseharian_kebersihan": "A",
    "keseharian_kerapian": "B",
    "keseharian_keterampilan": "A",
    "keseharian_kelakuan": "B",
    "keseharian_kerajinan": "A",
    "keseharian_kedisiplinan": "B",
    "keseharian_ketaatan": "A",
    "id_wali_kelas": 12,
    "updated_at": "2025-12-01T10:00:00.000000Z",
    "created_at": "2025-12-01T10:00:00.000000Z"
  }
}
```

## Catatan

- Wajib: `nomor_induk`, `kode_kelas`, `tahun_ajaran`, `semester`, dan **semua 7 aspek keseharian** (`kebersihan`, `kerapian`, `keterampilan`, `kelakuan`, `kerajinan`, `kedisiplinan`, `ketaatan`).
- Opsional: `id_wali_kelas`.
- Nilai keseharian hanya boleh diisi dengan `A`, `B`, `C`, atau `D`.
- Endpoint ini memakai mekanisme `updateOrCreate` pada tabel `data_raport` berdasarkan kombinasi `nomor_induk + tahun_ajaran + semester`.
