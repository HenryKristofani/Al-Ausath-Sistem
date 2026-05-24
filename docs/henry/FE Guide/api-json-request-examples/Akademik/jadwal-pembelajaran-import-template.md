# GET /api/data-master/jadwal-pembelajaran/import-template

Auth: `sanctum`

Endpoint ini dipakai untuk download template CSV import jadwal pembelajaran.

## Contoh request

```
GET /api/data-master/jadwal-pembelajaran/import-template
```

## Contoh response sukses

- File `template-import-jadwal-pembelajaran.csv`
- Header kolom:

```
id_kelas_mapel,tahun_ajaran,hari,jam_mulai,jam_selesai,ruangan,status
```
