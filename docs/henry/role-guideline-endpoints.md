# Role Guideline Per Endpoint (Backend API)

Dokumen ini menjelaskan siapa yang boleh mengakses setiap endpoint API berdasarkan implementasi kode saat ini.

Sumber acuan implementasi:

- routes API: backend/routes/api.php
- aturan role khusus KKM: backend/app/Http/Controllers/Api/Akademik/KkmMapelController.php

## 1. Aturan Umum Autentikasi

1. Endpoint berikut ini terbuka tanpa login:

- POST /api/login
- POST /api/register

2. Endpoint lain memakai auth:sanctum.

- User harus sudah login dan membawa token/session yang valid.

3. Guard user yang dipakai aplikasi:

- petugas
- santri

4. Nilai role disimpan di kolom peran_akun (khusus akun petugas).

## 2. Nilai peran_akun Yang Direkomendasikan

Gunakan nilai baku ini agar tidak salah akses:

- guru_mapel
- admin

Catatan: kode saat ini juga menerima variasi berikut untuk kompatibilitas:

- guru mapel
- mapel
- administrator

## 3. Matriks Akses Endpoint

Keterangan kolom:

- Auth: apakah butuh auth:sanctum
- Petugas: apakah akun petugas boleh akses
- Santri: apakah akun santri boleh akses
- Role Detail: pembatasan tambahan berdasarkan peran_akun

| Endpoint                                         | Method | Auth  | Petugas | Santri               | Role Detail                                                      |
| ------------------------------------------------ | ------ | ----- | ------- | -------------------- | ---------------------------------------------------------------- |
| /api/login                                       | POST   | Tidak | Ya      | Ya                   | Role dikirim di payload login (petugas atau santri)              |
| /api/register                                    | POST   | Tidak | Ya      | Ya                   | Role dikirim di payload register (petugas atau santri)           |
| /api/logout                                      | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/me                                          | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot                              | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot                              | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/set-default                  | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/{id}                         | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/{id}                         | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/{id}                         | DELETE | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/kkm-mapel                          | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/kkm-mapel                          | POST   | Ya    | Ya      | Tidak dibatasi route | Hanya petugas role guru_mapel/guru mapel/mapel                   |
| /api/akademik/kkm-mapel/{id}                     | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/kkm-mapel/{id}                     | PUT    | Ya    | Ya      | Tidak dibatasi route | Petugas role guru_mapel/guru mapel/mapel dan admin/administrator |
| /api/akademik/kkm-mapel/{id}                     | DELETE | Ya    | Ya      | Tidak dibatasi route | Hanya petugas role admin/administrator (override)                |
| /api/akademik/konversi-nilai                     | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai                     | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai/{id}                | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai/{id}                | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai/{id}                | DELETE | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-akhlak                       | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-akhlak                       | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/keseharian                  | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/keseharian                  | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/catatan-wali                | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/catatan-wali                | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar                 | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar                 | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}            | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}            | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}            | DELETE | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}/berkas     | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}/tes        | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}/verifikasi | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/ppdb/pendaftar/{id}/notifikasi | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/santri                         | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/santri                         | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/santri/{id}                    | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/santri/{id}                    | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/santri/{id}                    | DELETE | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/setting                    | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/setting                    | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/setting/{id}               | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/setting/{id}               | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/setting/{id}               | DELETE | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/pembayaran                 | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/pembayaran                 | POST   | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/pembayaran/{id}            | GET    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/pembayaran/{id}            | PUT    | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |
| /api/administrasi/spp/pembayaran/{id}            | DELETE | Ya    | Ya      | Ya                   | Tidak ada cek peran_akun tambahan                                |

## 4. Catatan Implementasi Penting

1. Pembatasan role level endpoint yang benar-benar aktif saat ini baru diterapkan pada mutasi KKM mapel (POST, PUT, DELETE).
2. Endpoint lain masih menggunakan pembatasan level autentikasi (auth:sanctum) tanpa validasi peran_akun per endpoint.
3. Jika dibutuhkan kebijakan role yang lebih ketat untuk endpoint lain, perlu penambahan policy/middleware/guard logic pada controller terkait.
