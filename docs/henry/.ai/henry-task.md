# Henry Kristofani — Task Plan: Modul E-Rapor (Sesuai Client)

> Referensi utama: docs/client-flow.md
> Scope: Backend API only.
> Prinsip: mengikuti kebijakan client tanpa asumsi tambahan.

---

## Fase 1 — Finalisasi Aturan Data (Sudah Ada + Penyesuaian)

### 1.1 Bobot Nilai Global

- [x] Tabel `bobot_nilai` sudah dibuat.
- [x] Tetapkan kebijakan aktif: bobot sama semua mapel (20/30/50).
- [x] Batasi penggunaan ke mode global (tidak dibedakan per jenjang).
- [x] Validasi total bobot = 100.

### 1.2 KKM per Mapel (Checker Status)

- [x] Tabel `kkm_mapel` sudah dibuat.
- [x] Terapkan kebijakan KKM sama antar jenjang (tidak beda MTQ/MTS/Aliyah).
- [x] KKM dipakai hanya untuk cek status nilai akhir: melampaui batas atau tidak.
- [x] KKM bukan komponen perhitungan nilai akhir.

### 1.3 Nilai Akhlak dan Keseharian

- [x] Tabel `nilai_akhlak` sudah dibuat.
- [x] Sesuaikan implementasi ke kebutuhan client:
  - nilai akhlak/mapel cukup angka
  - komponen keseharian di rapor menggunakan A/B/C/D
- [x] Tambahkan endpoint/data field untuk keseharian dan catatan wali kelas.

---

## Fase 2 — API Konfigurasi Master

### 2.1 API Bobot

- [x] `BobotNilaiController` CRUD.
- [x] Endpoint khusus set bobot default 20/30/50.
- [x] Validasi total bobot = 100.

### 2.2 API KKM

- [x] `KkmMapelController` CRUD per mapel.
- [x] Setter utama KKM adalah guru mapel (petugas mapel), admin hanya untuk override terkontrol jika dibutuhkan.
- [x] Filter utama: `kode_mapel`, `tahun_ajaran`, `semester`.
- [x] Hilangkan ketergantungan logika beda jenjang pada proses hitung status.
- [x] Dukungan fallback global pada list KKM: jika `kode_unit` dikirim, ambil data spesifik unit lalu fallback ke `kode_unit = null`.
- [x] Prioritas hasil list KKM: data spesifik unit ditampilkan lebih dulu daripada data global.
- [x] Perbaikan constraint unik KKM agar mendukung kombinasi data global + data spesifik unit tanpa bentrok.
- [x] Tangani duplicate key KKM sebagai validasi `422` (bukan `500`) dengan pesan yang jelas.

### 2.3 API Konversi Nilai

- [x] `KonversiNilaiController` CRUD untuk konversi angka ke huruf/predikat.
- [x] Dukungan fallback global pada list konversi: jika `kode_unit` dikirim, ambil data spesifik unit lalu fallback ke `kode_unit = null`.
- [x] Prioritas hasil list konversi: data spesifik unit ditampilkan lebih dulu daripada data global.

### 2.4 Routing dan Security

- [x] Daftarkan route `api/akademik/*`.
- [x] Lindungi dengan `auth:sanctum`.

---

## Fase 3 — API Input Nilai (Sesuai Mekanisme Client)

Status fase: **DONE 100%** ✅

### 3.1 Input Komponen Nilai Mapel

- [x] Endpoint input nilai komponen:
  - nilai tugas (minimal 3 data)
  - nilai ulangan (minimal 3 data)
  - nilai ujian akhir
- [x] Validasi kriteria tugas yang diakui client:
  - PR
  - tugas pengganti saat pengajar tidak hadir
  - pengerjaan soal kompetensi/modul
- [x] Validasi kriteria ulangan yang diakui client:
  - soal disusun pengajar mapel
  - pengerjaan diawasi pengajar (tidak diwakilkan)
- [x] Simpan data per santri-mapel-semester.

### 3.2 Hitung Nilai Akhir Mapel

- [x] Hitung dari bobot global client:
  - tugas 20%
  - ulangan 30%
  - ujian akhir 50%
- [x] Terapkan pembulatan nilai mapel:
  - desimal 1-4 turun
  - desimal 5-9 naik

### 3.3 Normalisasi Nilai Tampil Rapor

- [x] Jika nilai akhir mapel = 100, tampilkan 98.
- [x] Jika nilai akhir mapel < 50, tampilkan 50 dengan flag merah.
- [x] Jika nilai akhir mapel = 50 asli, tampilkan 50 hitam.

### 3.4 Cek Status KKM

- [x] Setelah nilai final mapel didapat, bandingkan dengan KKM mapel.
- [x] Simpan/return status tuntas atau belum.
- [x] Pastikan KKM hanya checker, bukan penghitung nilai.

### 3.5 Input Nilai Akhlak dan Keseharian

- [x] Endpoint input nilai akhlak (angka).
- [x] Endpoint input keseharian anak (A/B/C/D: kebersihan, kerapian, keterampilan).
- [x] Endpoint catatan pengembangan diri oleh wali kelas.
- [x] Semua endpoint akademik wajib menolak request tanpa `nomor_induk` valid.

### 3.6 Penyempurnaan Implementasi Teknis (Sudah Selesai)

- [x] Endpoint detail nilai mapel menggunakan `kode_mapel` + `nomor_induk` (bukan `id_nilai`) agar sesuai alur user.
- [x] Item ulangan difilter terlebih dahulu: hanya `soal_disusun_pengajar=true` dan `diawasi_pengajar=true` yang ikut dihitung.
- [x] Validasi minimum 3 nilai ulangan **valid setelah filtering**.
- [x] Simpan hasil perhitungan akhir ke kolom terpisah: `nilai_akhir_mapel`, `nilai_rapor_tampil`, `flag_warna_rapor`.
- [x] Hapus kolom legacy `nilai_akhir` untuk mencegah mismatch hasil lama vs hasil baru.

---

## Fase 4 — API Generate Rapor

### 4.1 Rekap Rapor Semester

- [x] Agregasi nilai final mapel per santri.
- [x] Hitung rata-rata rapor (2 desimal, aturan pembulatan client).
- [x] Gabungkan absensi (sakit/izin/tanpa keterangan).
- [x] Gabungkan nilai akhlak, keseharian, catatan wali kelas.
- [x] Simpan ke `data_raport` status DRAFT.

### 4.2 Peringkat Kelas (Rumus Client)

- [x] Terapkan rumus:
  - [(nilai hifzh x 2) + (rata-rata diniyyah x 2) + (rata-rata umum x 1)] / 5
- [x] Simpan ranking per kelas.
- [x] Atur tampilan ranking:
  - top 10 untuk kelas besar
  - top 5 untuk kelas kecil

### 4.3 Terbitkan Rapor

- [x] Endpoint ubah status DRAFT ke TERBIT.
- [x] Set tanggal terbit.

### 4.4 Monitoring dan Listing Rapor

- [x] Endpoint GET untuk melihat daftar rapor yang sudah tergenerate.
- [x] Filter daftar rapor berdasarkan nama santri.
- [x] Filter daftar rapor berdasarkan status rapor (DRAFT/TERBIT).
- [x] Dukungan filter tambahan: `q`, `nomor_induk`, `kode_kelas`, `tahun_ajaran`, `semester`, `per_page`.

---

## Fase 5 — PDF dan Akses Santri

### 5.1 Generate PDF

- [x] Endpoint PDF rapor per santri.
- [x] Template universal semua jenjang dengan jumlah mapel dinamis.
- [x] Catat log download rapor.

### 5.1.1 Status Cetak DRAFT vs TERBIT

- [x] Cetak PDF status DRAFT diberi watermark `DRAFT`.
- [x] Cetak PDF status TERBIT tanpa watermark.

### 5.2 Self-Service Santri

- [x] Endpoint lihat rapor milik sendiri.
- [x] Endpoint download PDF milik sendiri.
- [x] Validasi ownership berdasarkan nomor induk.

---

## Catatan Implementasi Penting

- Ikuti kebijakan client apa adanya.
- Jangan menambah aturan akademik di luar dokumen client tanpa persetujuan.
- KKM khusus untuk status, bukan kalkulasi nilai akhir.
- Bobot global aktif adalah 20/30/50 dan sama untuk semua mapel.
- Nomor induk santri/wati wajib lengkap sebelum input nilai, generate rapor, maupun publish rapor.
- Catatan operasional non-API (SOP): lembar penilaian dibawa pengajar, direkap wali kelas, lalu diarsipkan sekretariat.

---

## Backlog BE Lanjutan (Pasca Fase 5)

### B1. Konversi Nilai Huruf per Mapel di Payload Rapor

- [x] Ambil rule konversi dari `data_konversi_nilai` (prioritas spesifik unit, fallback global).
- [x] Turunkan nilai huruf/predikat dari `nilai_rapor_tampil` tiap mapel.
- [x] Sertakan hasil konversi di payload API rapor (`show/list/self`) agar siap dipakai template PDF/klien.
- [x] Tambahkan field output konsisten per mapel, misal: `nilai_huruf` dan/atau `predikat`.

### B2. Konsistensi Flag Warna Nilai Rapor

- [x] Pastikan semua endpoint akademik yang menampilkan nilai mapel memakai sumber yang sama: `flag_warna_rapor` dari aturan nilai asli `< 50`.
- [x] Tambahkan uji kasus backend untuk skenario batas: `49.x -> 50 MERAH`, `50 asli -> 50 HITAM`, dan `100 -> 98 HITAM`.
- [x] Pastikan data lama/backfill (jika ada) tidak menimbulkan inkonsistensi antara `nilai_akhir_mapel`, `nilai_rapor_tampil`, dan `flag_warna_rapor` (tersedia command `php artisan raport:backfill-nilai-mapel`, aman dengan opsi `--dry-run`).

---

## Fase 6 — Statistik & Analytics Nilai

Status fase: **DONE 100%** ✅

### 6.1 API Statistik Nilai Santri

- [x] Endpoint statistik keseluruhan:
  - nilai rata-rata keseluruhan santri
  - nilai tertinggi
  - nilai terendah
  - jumlah santri yang sudah dinilai
- [x] Filter by: `kode_kelas`, `kode_mapel`, `tahun_ajaran`, `semester`
- [x] Controller: `NilaiStatistikController@index`
- [x] Endpoint: `GET /api/akademik/nilai-statistik/`

### 6.2 API Rata-rata Nilai per Kelas

- [x] Endpoint list kelas dengan rata-rata nilai per kelas.
- [x] Output: `kode_kelas`, `nama_kelas`, `rata_rata_nilai`, `jumlah_santri`.
- [x] Cocok untuk bar chart atau tabel perbandingan performa kelas.
- [x] Controller: `NilaiStatistikController@averagePerClass`
- [x] Endpoint: `GET /api/akademik/nilai-statistik/per-kelas`

### 6.3 API Grafik Perkembangan Nilai (Trend per Semester)

- [x] Endpoint tracking perkembangan per semester.
- [x] Format output: array per semester dengan nilai rata-rata.
- [x] Support filter: per santri, per kelas, per mata pelajaran.
- [x] Cocok untuk line chart.
- [x] Controller: `NilaiStatistikController@trendPerSemester`
- [x] Endpoint: `GET /api/akademik/nilai-statistik/trend`

### 6.4 API Identifikasi Santri Berprestasi

- [x] Kriteria: nilai rata-rata >= 85 (configurable).
- [x] Output: list santri top performers dengan nilai detail per mapel.
- [x] Filter by: `kode_kelas`, `tahun_ajaran`, `semester`, `threshold`, `limit`.
- [x] Controller: `NilaiStatistikController@topPerformers`
- [x] Endpoint: `GET /api/akademik/nilai-statistik/berprestasi`

### 6.5 API Identifikasi Santri Perlu Bimbingan

- [x] Kriteria: nilai < KKM atau rata-rata nilai rendah (configurable).
- [x] Output: list santri dengan mapel yang kurang memuaskan.
- [x] Include: mata pelajaran yang lemah, nilai akhir, status ketuntasan.
- [x] Filter by: `kode_kelas`, `tahun_ajaran`, `semester`, `threshold`, `limit`.
- [x] Controller: `NilaiStatistikController@needsHelp`
- [x] Endpoint: `GET /api/akademik/nilai-statistik/perlu-bimbingan`
- [x] Fix: PostgreSQL string literal quotes (single quotes untuk status comparison)

### 6.6 Routing dan Security

- [x] Daftarkan route `/api/akademik/nilai-statistik/*`.
- [x] Lindungi dengan `auth:sanctum`.
- [x] Role-based access: admin + pengajar bisa akses.

---

## Backlog Lanjutan (Setelah Fase 6)

### B3. Middleware Role-Based Access Control

- [ ] Buat middleware `CheckRoleAccess` untuk validasi `peran_akun`.
- [ ] Terapkan pada endpoint sensitif (akademik, administrasi).
- [ ] Validasi: `auth:sanctum` + role check.
- [ ] Priority: HIGH (blok akses yang seharusnya tidak boleh)

### B4. Dashboard Pengajar & Santri

- [ ] `DashboardPengajarController`: nilai, absensi, rekap per kelas diajar.
- [ ] `DashboardSantriController`: nilai sendiri, rapor, nilai akhlak.
- [ ] Route: `/api/akademik/dashboard`.
- [ ] Priority: HIGH (user engagement langsung)

### B5. Audit Trail / Riwayat Perubahan

- [ ] Catat siapa, kapan, apa yang diubah (nilai, rapor, schedule).
- [ ] Model: `LogAktivitas` atau extend existing `LogDownloadRaport`.
- [ ] Endpoint viewing: `/api/master/log-aktivitas`.
- [ ] Priority: MEDIUM (compliance + troubleshooting)

### B6. Schedule Enhancements

- [ ] Filter jadwal by tanggal (date range) + jenis kegiatan.
- [ ] Notifikasi (log DB) jika jadwal berubah.
- [ ] Export jadwal ke PDF (per kelas/pengajar/minggu).
- [ ] Controller: `DataJadwalPembelajaranController` enhancement.
- [ ] Priority: MEDIUM (UX polish)
