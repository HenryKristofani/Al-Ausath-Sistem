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

### 3.1 Input Komponen Nilai Mapel

- [ ] Endpoint input nilai komponen:
  - nilai tugas (minimal 3 data)
  - nilai ulangan (minimal 3 data)
  - nilai ujian akhir
- [ ] Validasi kriteria tugas yang diakui client:
  - PR
  - tugas pengganti saat pengajar tidak hadir
  - pengerjaan soal kompetensi/modul
- [ ] Validasi kriteria ulangan yang diakui client:
  - soal disusun pengajar mapel
  - pengerjaan diawasi pengajar (tidak diwakilkan)
- [ ] Simpan data per santri-mapel-semester.

### 3.2 Hitung Nilai Akhir Mapel

- [ ] Hitung dari bobot global client:
  - tugas 20%
  - ulangan 30%
  - ujian akhir 50%
- [ ] Terapkan pembulatan nilai mapel:
  - desimal 1-4 turun
  - desimal 5-9 naik

### 3.3 Normalisasi Nilai Tampil Rapor

- [ ] Jika nilai akhir mapel = 100, tampilkan 98.
- [ ] Jika nilai akhir mapel < 50, tampilkan 50 dengan flag merah.
- [ ] Jika nilai akhir mapel = 50 asli, tampilkan 50 hitam.

### 3.4 Cek Status KKM

- [ ] Setelah nilai final mapel didapat, bandingkan dengan KKM mapel.
- [ ] Simpan/return status tuntas atau belum.
- [ ] Pastikan KKM hanya checker, bukan penghitung nilai.

### 3.5 Input Nilai Akhlak dan Keseharian

- [ ] Endpoint input nilai akhlak (angka).
- [ ] Endpoint input keseharian anak (A/B/C/D: kebersihan, kerapian, keterampilan).
- [ ] Endpoint catatan pengembangan diri oleh wali kelas.
- [ ] Semua endpoint akademik wajib menolak request tanpa `nomor_induk` valid.

---

## Fase 4 — API Generate Rapor

### 4.1 Rekap Rapor Semester

- [ ] Agregasi nilai final mapel per santri.
- [ ] Hitung rata-rata rapor (2 desimal, aturan pembulatan client).
- [ ] Gabungkan absensi (sakit/izin/tanpa keterangan).
- [ ] Gabungkan nilai akhlak, keseharian, catatan wali kelas.
- [ ] Simpan ke `data_raport` status DRAFT.

### 4.2 Peringkat Kelas (Rumus Client)

- [ ] Terapkan rumus:
  - [(nilai hifzh x 2) + (rata-rata diniyyah x 2) + (rata-rata umum x 1)] / 5
- [ ] Simpan ranking per kelas.
- [ ] Atur tampilan ranking:
  - top 10 untuk kelas besar
  - top 5 untuk kelas kecil

### 4.3 Terbitkan Rapor

- [ ] Endpoint ubah status DRAFT ke TERBIT.
- [ ] Set tanggal terbit.

---

## Fase 5 — PDF dan Akses Santri

### 5.1 Generate PDF

- [ ] Endpoint PDF rapor per santri.
- [ ] Template universal semua jenjang dengan jumlah mapel dinamis.
- [ ] Catat log download rapor.

### 5.2 Self-Service Santri

- [ ] Endpoint lihat rapor milik sendiri.
- [ ] Endpoint download PDF milik sendiri.
- [ ] Validasi ownership berdasarkan nomor induk.

---

## Catatan Implementasi Penting

- Ikuti kebijakan client apa adanya.
- Jangan menambah aturan akademik di luar dokumen client tanpa persetujuan.
- KKM khusus untuk status, bukan kalkulasi nilai akhir.
- Bobot global aktif adalah 20/30/50 dan sama untuk semua mapel.
- Nomor induk santri/wati wajib lengkap sebelum input nilai, generate rapor, maupun publish rapor.
- Catatan operasional non-API (SOP): lembar penilaian dibawa pengajar, direkap wali kelas, lalu diarsipkan sekretariat.
