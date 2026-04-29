# POST /api/master/data-santri/{id}/buat-akun

Auth: `sanctum`

Endpoint ini dipakai untuk membuat akun login santri berdasarkan master data santri.

## Contoh request body

```json
{
  "nama_akun": "ahmad001",
  "password": "password123",
  "status": "AKTIF"
}
```

## Catatan

- Gunakan nilai `id_santri` sebagai path parameter `id`.
- `nama_akun` opsional, jika kosong akan menggunakan `nomor_induk` sebagai nama akun.
- `nama_akun` wajib unik pada tabel `data_akun_santri`.
- `password` wajib diisi minimal 6 karakter.
- `status` opsional, default nilai adalah `AKTIF`.
- Santri hanya dapat memiliki 1 akun. Jika sudah memiliki akun, endpoint akan mengembalikan error HTTP 422.
- Akun akan ditambahkan informasi dari master data santri seperti `nama_lengkap`, `nama_unit`, `nama_kelas`, dan `tahun_ajaran`.
