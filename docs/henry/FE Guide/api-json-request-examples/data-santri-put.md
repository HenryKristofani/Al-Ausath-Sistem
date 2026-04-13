# PUT /api/master/data-santri/{id}

Auth: `sanctum`

Endpoint ini dipakai untuk memperbarui data santri.

## Contoh request body

```json
{
  "nomor_induk": "001",
  "nama_lengkap_santri": "Ahmad Hidayat",
  "kode_kelas": "X-IPA-1",
  "status": "AKTIF",
  "tahun_masuk": 2024,
  "jenis_kelamin": "L",
  "tempat_lahir": "Bandung",
  "tanggal_lahir": "2008-05-15",
  "agama": "Islam",
  "berat_badan": 65.5,
  "tinggi_badan": 175,
  "gol_darah": "O",
  "provinsi": "Jawa Barat",
  "kota_kabupaten": "Bandung",
  "kecamatan": "Bandung",
  "kelurahan": "Bandung",
  "alamat_tinggal": "Jl. Merdeka No. 10",
  "nomor_telepon": "08123456789",
  "alamat_email": "ahmad@example.com",
  "nama_ayah_kandung": "Budi Hidayat",
  "nama_ibu_kandung": "Siti Nurhayati",
  "nama_wali": "Budi Hidayat"
}
```

## Catatan

- Semua field opsional (dapat mengupdate field tertentu saja).
- `nomor_induk` tidak boleh duplikat dengan santri lain (kecuali santri yang sama).
- `kode_kelas` harus ada pada tabel `data_kelas` jika diubah.
- `tanggal_lahir` harus dalam format `YYYY-MM-DD`.
- Response akan include relasi `kelas` dan `akun` yang ter-update.
