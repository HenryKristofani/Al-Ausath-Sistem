# E-Rapor Flow (Sesuai Brief Client) — Al-Ausath Sistem

Dokumen ini disusun langsung dari kebutuhan di docs/client-flow.md.

## 1. Prinsip Kebijakan Nilai Client

1. Pengajar mengisi nilai berdasarkan lembar penilaian mapel.
2. Bobot sama untuk semua mapel:
   - Tugas: 20%
   - Ulangan: 30%
   - Ujian Akhir: 50%
3. KKM dipakai hanya untuk cek status nilai akhir (melampaui batas KKM atau belum).
4. KKM antar jenjang tidak dibedakan (kebijakan sama lintas jenjang).
5. Nilai rapor mapel mengikuti aturan presentasi:
   - Nilai maksimal yang ditulis di rapor: 98
   - Nilai di bawah 50 ditulis 50 (merah)
   - Nilai 50 asli ditulis 50 (hitam)

## 2. Aktor dan Hak Akses

1. Admin Akademik
   - Kelola data KKM mapel
   - Kelola referensi bobot (global)
   - Generate dan terbitkan rapor
2. Pengajar
   - Input nilai komponen mapel (tugas, ulangan, ujian akhir) atau nilai akhir sesuai kebijakan operasional
   - Input nilai akhlak/mapel akhlak
3. Wali Kelas
   - Rekap nilai dari pengajar
   - Isi catatan perkembangan diri santri
4. Santri
   - Lihat dan unduh rapor milik sendiri

## 3. Flow 0 — Login dan Otorisasi

1. User login melalui endpoint auth.
2. Backend mengembalikan role (petugas atau santri).
3. Endpoint akademik dilindungi auth:sanctum.
4. Santri hanya boleh akses rapor miliknya sendiri.

## 4. Flow 1 — Setup Referensi Awal Semester

### 4.1 Bobot Global
1. Admin set bobot global 20/30/50.
2. Bobot berlaku sama untuk semua mapel.
3. Bobot ini menjadi acuan resmi sistem dan dokumen rapor.

### 4.2 KKM Mapel
1. Admin/petugas mapel set KKM per mapel.
2. KKM diperlakukan sama antar jenjang (tidak dibedakan).
3. KKM hanya untuk cek status nilai akhir, bukan menghitung nilai akhir.

## 5. Flow 2 — Input Nilai Mapel

Langkah operasional:
1. Pengajar mengisi lembar penilaian mapel (minimal 3 tugas, minimal 3 ulangan, dan nilai ujian akhir).
2. Nilai komponen dirata-ratakan sesuai ketentuan client.
3. Sistem hitung nilai akhir mapel dengan bobot 20/30/50.
4. Sistem lakukan normalisasi nilai rapor mapel:
   - jika 100 maka ditulis 98
   - jika kurang dari 50 maka ditulis 50 (flag warna merah)
   - jika sama dengan 50 asli maka ditulis 50 (hitam)
5. Sistem bandingkan nilai rapor mapel terhadap KKM mapel untuk status tuntas/belum.

## 6. Flow 3 — Aturan Pembulatan

### 6.1 Pembulatan Nilai Mapel
1. Angka desimal 1-4 dibuang.
2. Angka desimal 5-9 dibulatkan ke atas.

### 6.2 Pembulatan Rata-Rata Rapor
1. Rata-rata rapor disimpan 2 digit desimal.
2. Digit ke-3 desimal 1-4 dibuang, 5-9 dibulatkan naik.

## 7. Flow 4 — Generate Rapor Semester

1. Admin/wali kelas trigger generate rapor per kelas dan semester.
2. Sistem gabungkan semua nilai mapel final yang sudah dinormalisasi.
3. Sistem hitung rata-rata rapor.
4. Sistem hitung peringkat kelas memakai rumus client:
   - [(nilai hifzh x 2) + (rata-rata diniyyah x 2) + (rata-rata umum x 1)] / 5
5. Sistem tentukan daftar ranking yang ditampilkan:
   - top 10 untuk kelas besar
   - top 5 untuk kelas kecil
6. Sistem gabungkan komponen pendukung rapor:
   - absensi (sakit/izin/tanpa keterangan)
   - keseharian anak (A/B/C/D)
   - catatan pengembangan diri dari wali kelas
7. Simpan ke data_raport status DRAFT, lalu TERBIT saat final.

## 8. Flow 5 — Generate PDF Rapor

1. FE request endpoint PDF rapor.
2. Backend render template rapor universal.
3. Tabel mapel dinamis sesuai jumlah mapel.
4. Return file PDF (application/pdf) dan catat log download.

## 9. Flow 6 — Self-Service Santri

1. Santri login.
2. Santri membuka rapor sendiri.
3. Backend cek kepemilikan data.
4. Jika valid, kirim data dan PDF.

## 10. Error Handling Wajib

1. Nilai komponen belum memenuhi syarat minimal (misal tugas/ulangan kurang dari 3) menghasilkan warning validasi.
2. KKM mapel belum diset, nilai tetap tersimpan, status tuntas belum dihitung.
3. Data mapel/absensi/catatan belum lengkap, rapor tetap DRAFT.
4. Akses rapor bukan milik user return 403.

## 11. Urutan Implementasi API

1. Endpoint konfigurasi bobot global
2. Endpoint KKM mapel
3. Endpoint input nilai mapel plus normalisasi nilai rapor
4. Endpoint input komponen keseharian dan catatan wali kelas
5. Endpoint generate rapor plus ranking
6. Endpoint PDF plus log download
7. Endpoint akses rapor santri

---

Dokumen ini menjadi acuan flow final yang diselaraskan dengan kebutuhan client.
