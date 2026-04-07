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

## 3. Matriks Akses Endpoint (Scope Khusus)

Dokumen ini hanya memuat endpoint berikut:

- bobot nilai
- kkm mapel
- konversi nilai
- nilai akhlak
- nilai mapel
- raport catatan wali
- raport generate
- raport pdf

Keterangan kolom:

- Auth: apakah butuh auth:sanctum
- Petugas: apakah akun petugas boleh akses
- Santri: apakah akun santri boleh akses
- Role Detail: pembatasan tambahan berdasarkan peran_akun

| Endpoint                               | Method | Auth | Petugas | Santri | Role Detail                                                      |
| -------------------------------------- | ------ | ---- | ------- | ------ | ---------------------------------------------------------------- |
| /api/akademik/bobot                    | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot                    | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/set-default        | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/{id}               | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/{id}               | PUT    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/bobot/{id}               | DELETE | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/kkm-mapel                | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/kkm-mapel                | POST   | Ya   | Ya      | Tidak  | Hanya petugas role guru_mapel/guru mapel/mapel                   |
| /api/akademik/kkm-mapel/{id}           | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/kkm-mapel/{id}           | PUT    | Ya   | Ya      | Tidak  | Petugas role guru_mapel/guru mapel/mapel dan admin/administrator |
| /api/akademik/kkm-mapel/{id}           | DELETE | Ya   | Ya      | Tidak  | Hanya petugas role admin/administrator (override)                |
| /api/akademik/konversi-nilai           | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai           | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai/{id}      | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai/{id}      | PUT    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/konversi-nilai/{id}      | DELETE | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-akhlak             | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-akhlak             | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-mapel              | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-mapel              | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/nilai-mapel/{kode_mapel} | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/catatan-wali      | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/catatan-wali      | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport                   | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/show              | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/generate          | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/rank              | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/publish           | POST   | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/pdf               | GET    | Ya   | Ya      | Ya     | Tidak ada cek peran_akun tambahan                                |
| /api/akademik/raport/self/pdf          | GET    | Ya   | Tidak   | Ya     | Hanya akun santri (self-service)                                 |

## 4. Catatan Implementasi Penting

1. Pembatasan role level endpoint yang benar-benar aktif saat ini baru diterapkan pada mutasi KKM mapel (POST, PUT, DELETE).
2. Endpoint PDF self-service rapor (`/api/akademik/raport/self/pdf`) dibatasi untuk akun santri.
3. Endpoint lain pada scope dokumen ini masih menggunakan pembatasan level autentikasi (auth:sanctum) tanpa validasi peran_akun per endpoint.
4. Jika dibutuhkan kebijakan role yang lebih ketat untuk endpoint lain, perlu penambahan policy/middleware/guard logic pada controller terkait.
