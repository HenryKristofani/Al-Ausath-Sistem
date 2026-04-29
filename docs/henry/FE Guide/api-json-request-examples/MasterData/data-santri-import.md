# POST /api/master/data-santri/import

Auth: `sanctum`

Endpoint ini dipakai untuk import data santri dari file CSV.

## Contoh request

```
Content-Type: multipart/form-data

file: <CSV file>
```

## Format CSV

Header yang diharapkan:

```
nomor_induk,nama_lengkap_santri,kode_kelas,status,tahun_masuk,tahun_lulus,jenis_kelamin,tempat_lahir,tanggal_lahir,agama,berat_badan,tinggi_badan,gol_darah,provinsi,kota_kabupaten,kecamatan,kelurahan,alamat_tinggal,nomor_telepon,alamat_email,nama_ayah_kandung,nama_ibu_kandung,nama_wali
```

Contoh data:

```
001,Ahmad Hidayat,X-IPA-1,AKTIF,2024,,L,Bandung,2008-05-15,Islam,65.5,175,O,Jawa Barat,Bandung,Bandung,Bandung,Jl. Merdeka No. 10,08123456789,ahmad@example.com,Budi Hidayat,Siti Nurhayati,Budi Hidayat
```

## Catatan

- File harus berformat CSV atau TXT.
- Import menggunakan strategi upsert berdasarkan `nomor_induk`.
- Jika `nomor_induk` sudah ada, data akan diupdate. Jika belum ada, data akan dibuat baru.
- `kode_kelas` harus ada pada tabel `data_kelas`.
- Response akan mengembalikan summary import termasuk jumlah data yang dibuat dan diupdate.
