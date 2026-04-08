# Backend to Frontend Guide: View dan Role

Dokumen ini dibuat agar tim FE tidak bingung saat menentukan:

1. halaman apa yang harus dibuat,
2. halaman itu untuk role siapa,
3. endpoint backend mana yang dipakai,
4. field form apa yang wajib ada.

Referensi backend:

- routes: backend/routes/api.php
- role guideline: docs/henry/role-guideline-endpoints.md

## 1. Ringkas Role dan Akses

## 1.1 Role Login Utama

- `petugas`
- `santri`

Endpoint auth:

- `POST /api/login`
- `POST /api/register`
- `POST /api/logout`
- `GET /api/me`

Payload login wajib:

- `role` (petugas|santri)
- `username`
- `password`

Catatan FE:

- Setelah login, selalu panggil `GET /api/me` untuk hydrate user state.
- Simpan `role` dan data user (`nomor_induk`, `peran_akun`) di global store FE.

## 1.2 Sub-role Petugas (untuk pembagian menu FE)

Dari model `DataPetugas::PERAN_AKUN_OPTIONS`:

- Petugas Admin
- Petugas Tata Usaha
- Petugas PPDB
- Staf Pengajar

Catatan penting:

- Beberapa endpoint KKM cek role string `guru_mapel/admin` (legacy teknis), bukan label UI di atas.
- FE tetap render menu berdasarkan `peran_akun` dari response login/me.
- Jika user kena `403` di aksi tertentu, tampilkan pesan backend apa adanya.

## 2. Peta Menu FE per Role

## 2.1 Santri

Menu minimum:

- Dashboard Santri
- Rapor Saya
- Download Rapor PDF
- Profil Saya (opsional)

Endpoint utama:

- `GET /api/me`
- `GET /api/akademik/raport/self`
- `GET /api/akademik/raport/self/pdf`

## 2.2 Petugas Admin

Menu minimum:

- Dashboard Admin
- Master Data: Unit, Kelas, Tahun Ajaran, Petugas, Santri, Akun Santri
- Akademik: Bobot, KKM, Konversi Nilai, Nilai Mapel, Nilai Akhlak, Keseharian, Catatan Wali
- Rapor: Generate, Ranking, Publish, Preview, PDF
- Administrasi: PPDB, SPP Setting, Pembayaran SPP

## 2.3 Petugas Tata Usaha

Menu minimum:

- Master Data: Santri, Akun Santri, Kelas
- Administrasi Keuangan: SPP Setting, Pembayaran SPP
- Monitoring Rapor (read mostly, sesuai kebijakan internal)

## 2.4 Petugas PPDB

Menu minimum:

- PPDB Pendaftar
- PPDB Berkas
- PPDB Tes
- PPDB Verifikasi
- PPDB Notifikasi

## 2.5 Staf Pengajar

Menu minimum:

- Input Nilai Mapel
- Nilai Akhlak
- Keseharian
- Catatan Wali
- Rapor per santri (show/preview)

## 3. Blueprint Halaman FE per Modul

## 3.1 Auth

Halaman:

- Login
- Register (opsional jika dipakai operasional)

Komponen wajib Login:

- Select role (`petugas`/`santri`)
- Input username
- Input password
- Submit

Aksi sukses login:

1. Call `POST /api/login`
2. Call `GET /api/me`
3. Redirect by role:
   - santri -> `/santri/rapor`
   - petugas -> `/petugas/dashboard`

## 3.2 Akademik - Bobot Nilai

Halaman:

- List Bobot
- Form Tambah/Edit Bobot

Endpoint:

- `GET /api/akademik/bobot`
- `POST /api/akademik/bobot`
- `PUT /api/akademik/bobot/{id}`
- `DELETE /api/akademik/bobot/{id}`
- `POST /api/akademik/bobot/set-default`

Komponen View:

- Table list + pagination
- Filter (jika disediakan di backend)
- Form input bobot tugas/ulangan/ujian akhir
- Tombol set default 20/30/50

## 3.3 Akademik - KKM Mapel

Halaman:

- List KKM
- Form Tambah/Edit KKM

Endpoint:

- `GET /api/akademik/kkm-mapel`
- `POST /api/akademik/kkm-mapel`
- `PUT /api/akademik/kkm-mapel/{id}`
- `DELETE /api/akademik/kkm-mapel/{id}`

Filter list yang perlu ada di FE:

- `kode_mapel`
- `tahun_ajaran`
- `semester`
- `kode_unit` (support fallback unit/global dari backend)
- `per_page`

Field form wajib:

- `kode_mapel`
- `tahun_ajaran`
- `semester` (1/2)
- `nilai_kkm`

Field opsional:

- `kode_unit`
- `keterangan`

Catatan behavior:

- Aksi bisa gagal `403` karena pembatasan role di backend.
- Aksi create/update bisa gagal `422` jika kombinasi data duplikat.

## 3.4 Akademik - Konversi Nilai

Halaman:

- List Konversi
- Form Tambah/Edit Konversi

Endpoint:

- `GET /api/akademik/konversi-nilai`
- `POST /api/akademik/konversi-nilai`
- `PUT /api/akademik/konversi-nilai/{id}`
- `DELETE /api/akademik/konversi-nilai/{id}`

Komponen form utama:

- rentang nilai (`nilai_min`, `nilai_max`)
- `nilai_huruf`
- `predikat`
- status aktif/nonaktif (jika ada field)
- `kode_unit` opsional

## 3.5 Akademik - Input Nilai Mapel

Halaman:

- List nilai mapel per santri
- Form input/edit nilai mapel
- Detail nilai mapel by mapel+santri

Endpoint:

- `GET /api/akademik/nilai-mapel`
- `POST /api/akademik/nilai-mapel`
- `GET /api/akademik/nilai-mapel/{kode_mapel}`

Query list wajib:

- `nomor_induk`

Query opsional:

- `kode_mapel`
- `kode_kelas`
- `tahun_ajaran`
- `semester`
- `per_page`

Payload upsert wajib:

- `nomor_induk`
- `kode_mapel`
- `kode_kelas`
- `tahun_ajaran`
- `semester`
- `tugas[]` minimal 3 item
- `ulangan[]` minimal 3 item
- `ujian_akhir`

Struktur item tugas:

- `nilai`
- `jenis` (PR|TUGAS_PENGGANTI|MODUL_KOMPETENSI)

Struktur item ulangan:

- `nilai`
- `soal_disusun_pengajar` (boolean)
- `diawasi_pengajar` (boolean)

Field opsional:

- `id_petugas_input`
- `keterangan`

UX penting:

- FE harus dukung dynamic rows untuk tugas/ulangan.
- Tampilkan hasil perhitungan dari response (`nilai_rapor_tampil`, `flag_warna_rapor`, status KKM).
- Simpan dan tampilkan field persist `status_ketuntasan` dari data nilai mapel (`TUNTAS`/`BELUM_TUNTAS`) agar konsisten antara list, detail, dan laporan.
- Bila backend return 422 soal ulangan valid < 3, tampilkan message persis backend.

## 3.6 Akademik - Nilai Akhlak

Halaman:

- List nilai akhlak per santri
- Form upsert nilai akhlak

Endpoint:

- `GET /api/akademik/nilai-akhlak`
- `POST /api/akademik/nilai-akhlak`

Query list wajib:

- `nomor_induk`

Form upsert wajib:

- `nomor_induk`
- `tahun_ajaran`
- `semester`
- `nilai_angka`

Form opsional:

- `aspek` (default AKHLAK)
- `deskripsi`
- `id_petugas_input`

## 3.7 Akademik - Keseharian dan Catatan Wali

Halaman:

- Form keseharian rapor
- Form catatan wali

Endpoint:

- `GET /api/akademik/raport/keseharian`
- `POST /api/akademik/raport/keseharian`
- `GET /api/akademik/raport/catatan-wali`
- `POST /api/akademik/raport/catatan-wali`

Komponen FE:

- Input skala A/B/C/D untuk kebersihan, kerapian, keterampilan
- Textarea catatan pengembangan diri
- Selector santri + tahun ajaran + semester

## 3.8 Akademik - Rapor Operasional Petugas

Halaman:

- List rapor
- Detail rapor
- Generate rapor
- Ranking kelas
- Publish rapor
- Download PDF rapor

Endpoint:

- `GET /api/akademik/raport`
- `GET /api/akademik/raport/show`
- `POST /api/akademik/raport/generate`
- `POST /api/akademik/raport/rank`
- `POST /api/akademik/raport/publish`
- `GET /api/akademik/raport/pdf`

Filter list rapor yang perlu di FE:

- `q`
- `nama`
- `status` (DRAFT|TERBIT)
- `nomor_induk`
- `kode_kelas`
- `tahun_ajaran`
- `semester`
- `include_nilai_mapel` (boolean)
- `per_page`

Payload aksi:

- Generate: `nomor_induk`, `tahun_ajaran`, `semester`
- Rank: `kode_kelas`, `tahun_ajaran`, `semester`
- Publish: `kode_kelas`, `tahun_ajaran`, `semester`, optional `nomor_induk`, optional `tanggal_terbit`
- PDF: `nomor_induk`, `tahun_ajaran`, `semester`

UX penting:

- Pisahkan status badge DRAFT vs TERBIT.
- Saat download PDF, trigger file download (blob/file response).
- Untuk list besar, wajib server-side pagination.

## 3.9 Rapor Self Service Santri

Halaman:

- Rapor Saya
- Download Rapor Saya

Endpoint:

- `GET /api/akademik/raport/self`
- `GET /api/akademik/raport/self/pdf`

Query wajib:

- `tahun_ajaran`
- `semester`

Catatan FE:

- Tidak perlu input nomor induk dari user (backend resolve dari akun santri login).

## 3.10 Administrasi - PPDB

Halaman:

- List Pendaftar
- Form Pendaftar
- Detail Pendaftar
- Tab Berkas
- Tab Tes
- Tab Verifikasi
- Tab Notifikasi

Endpoint:

- `GET /api/administrasi/ppdb/pendaftar`
- `POST /api/administrasi/ppdb/pendaftar`
- `PUT /api/administrasi/ppdb/pendaftar/{id}`
- `DELETE /api/administrasi/ppdb/pendaftar/{id}`
- `POST /api/administrasi/ppdb/pendaftar/{id}/berkas`
- `PUT /api/administrasi/ppdb/pendaftar/{id}/tes`
- `PUT /api/administrasi/ppdb/pendaftar/{id}/verifikasi`
- `POST /api/administrasi/ppdb/pendaftar/{id}/notifikasi`

Filter list FE:

- `status_verifikasi`
- `jenjang`
- `q`
- `per_page`

## 3.11 Administrasi - Master Santri

Halaman:

- List Santri
- Form Santri
- Detail Santri
- Import/Export Santri
- Pindah Kelas Massal
- Buat Akun dari Santri

Endpoint utama:

- `GET /api/administrasi/santri`
- `POST /api/administrasi/santri`
- `PUT /api/administrasi/santri/{id}`
- `DELETE /api/administrasi/santri/{id}`
- `POST /api/administrasi/santri/import`
- `GET /api/administrasi/santri/export`
- `GET /api/administrasi/santri/import-template`
- `POST /api/administrasi/santri/pindah-kelas`
- `POST /api/administrasi/santri/{id}/buat-akun`

## 3.12 Administrasi - Akun Santri

Halaman:

- List Akun Santri
- Form Akun Santri
- Sinkron Akun Massal
- Kelas Tanpa Akun
- Santri Tanpa Akun per Kelas

Endpoint utama:

- `GET /api/administrasi/akun-santri`
- `POST /api/administrasi/akun-santri`
- `PUT /api/administrasi/akun-santri/{id}`
- `DELETE /api/administrasi/akun-santri/{id}`
- `POST /api/administrasi/akun-santri/sinkron`
- `GET /api/administrasi/akun-santri/kelas-tanpa-akun`
- `GET /api/administrasi/akun-santri/santri-tanpa-akun?kode_kelas=...`
- `GET /api/administrasi/akun-santri/export`

## 3.13 Administrasi - Petugas

Halaman:

- List Petugas
- Form Petugas
- Import/Export Petugas

Endpoint utama:

- `GET /api/administrasi/petugas/peran-akun-options`
- `GET /api/administrasi/petugas`
- `POST /api/administrasi/petugas`
- `PUT /api/administrasi/petugas/{id}`
- `DELETE /api/administrasi/petugas/{id}`
- `POST /api/administrasi/petugas/import`
- `GET /api/administrasi/petugas/export`
- `GET /api/administrasi/petugas/import-template`

## 3.14 Administrasi - SPP

Halaman:

- SPP Setting List/Form
- Pembayaran SPP List/Form

Endpoint setting:

- `GET /api/administrasi/spp/setting`
- `POST /api/administrasi/spp/setting`
- `PUT /api/administrasi/spp/setting/{id}`
- `DELETE /api/administrasi/spp/setting/{id}`

Endpoint pembayaran:

- `GET /api/administrasi/spp/pembayaran`
- `POST /api/administrasi/spp/pembayaran`
- `PUT /api/administrasi/spp/pembayaran/{id}`
- `DELETE /api/administrasi/spp/pembayaran/{id}`

Filter pembayaran di FE:

- `id_santri`
- `status`
- `tanggal_mulai`
- `tanggal_selesai`
- `per_page`

## 4. Rekomendasi Struktur Routing FE

Contoh struktur route FE:

- `/login`
- `/petugas/dashboard`
- `/petugas/akademik/bobot`
- `/petugas/akademik/kkm`
- `/petugas/akademik/konversi`
- `/petugas/akademik/nilai-mapel`
- `/petugas/akademik/nilai-akhlak`
- `/petugas/akademik/rapor`
- `/petugas/administrasi/ppdb`
- `/petugas/administrasi/santri`
- `/petugas/administrasi/akun-santri`
- `/petugas/administrasi/petugas`
- `/petugas/administrasi/spp/setting`
- `/petugas/administrasi/spp/pembayaran`
- `/santri/rapor`

Gunakan route guard:

- Guest only: `/login`
- Auth role petugas: semua `/petugas/*`
- Auth role santri: semua `/santri/*`

## 5. Standar Komponen Wajib di Hampir Semua Halaman

Untuk konsistensi FE, hampir semua halaman list sebaiknya punya:

- Search input (`q`)
- Filter select sesuai endpoint
- Table + server pagination
- Action column: detail/edit/delete (sesuai izin)
- Empty state
- Loading state
- Error state (tampilkan message backend)

Untuk form:

- Inline validation FE
- Tampilkan error 422 per field dari backend
- Disable tombol submit saat loading
- Toast sukses/gagal

## 6. Prioritas Implementasi FE (Agar Cepat Jalan)

Urutan sprint yang disarankan:

1. Auth + role guard + layout dasar.
2. Rapor self service santri.
3. Akademik petugas: nilai mapel, akhlak, keseharian, catatan wali.
4. Workflow rapor petugas: generate, rank, publish, pdf.
5. Master data administrasi (santri, akun, petugas, PPDB, SPP).

Dengan urutan ini, fitur inti client (penilaian dan rapor) bisa dipakai lebih cepat.
