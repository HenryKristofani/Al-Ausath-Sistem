# GET /api/master/data-santri/import-template

Auth: `sanctum`

Endpoint ini dipakai untuk mendownload template file CSV untuk import data santri.

## Contoh request

```json
{
  "query": {}
}
```

## Respons

Response akan mengembalikan file CSV berisi header kolom yang valid untuk import data santri:

```
nomor_induk,nama_lengkap_santri,kode_kelas,status,tahun_masuk,tahun_lulus,jenis_kelamin,tempat_lahir,tanggal_lahir,agama,berat_badan,tinggi_badan,gol_darah,provinsi,kota_kabupaten,kecamatan,kelurahan,alamat_tinggal,nomor_telepon,alamat_email,nama_ayah_kandung,nama_ibu_kandung,nama_wali
```

## Catatan

- File adalah template kosong berisi hanya header kolom.
- Gunakan template ini sebagai panduan untuk membuat file CSV yang akan diimport.
- Tambahkan data santri pada baris berikutnya setelah header.
