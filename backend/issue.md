# Daftar Perbaikan dan Pengembangan Sistem Pendaftaran Santri

## Modul PPDB

1. **Ujian Masuk Mendukung Bahasa Arab**

   * Tambahkan opsi pembuatan soal ujian dalam Bahasa Arab.
   * Pastikan tampilan mendukung penulisan Arab (RTL/Right-to-Left).

2. **Pembuatan Soal Pilihan Ganda dan Upload Gambar**

   * Admin dapat membuat soal pilihan ganda.
   * Setiap soal dapat dilengkapi gambar pendukung.
   * Mendukung upload, preview, dan penyimpanan gambar.

---

## Modul PPDB

3. **Pemilihan Kelas Dipermudah**

   * Sederhanakan proses pemilihan kelas saat pendaftaran.
   * Tampilkan kelas yang tersedia sesuai jenjang dan kuota.

4. **Satu Email untuk Beberapa Siswa**

   * Tentukan dan implementasikan kebijakan:

     * Apakah satu email dapat digunakan untuk mendaftarkan lebih dari satu siswa?
   * Jika ya, tambahkan relasi akun wali dengan banyak siswa. iya bisa ketika mendaftarkan saudaranya gimana ya caranya?

5. **Upload Bukti Orang Tua Guru**

   * Tambahkan fitur upload dokumen pendukung jika orang tua merupakan guru.
   * Dokumen dapat diverifikasi oleh admin.

6. **Filter Kelas**

   * Tambahkan fitur filter berdasarkan:

     * Jenjang
     * Tahun Ajaran
     * Kelas
     * Status Kelas

---

## Modul SSPP

7. **Perhitungan Tunggakan**

   * Pastikan sistem dapat menghitung tunggakan secara otomatis.
   * Tunggakan harus memperhitungkan:

     * SPP yang belum dibayar
     * Infaq yang belum dibayar
     * Tagihan lainnya

8. **Siswa Pindahan dan SPP**

   * Tentukan alur pembayaran untuk siswa pindahan.
   * Sistem harus menghitung SPP sesuai tanggal masuk atau kebijakan sekolah.

9. **Uang Gedung dan SPP Pertama Digabung**

   * Saat pembayaran awal, tagihan Uang Gedung dan SPP bulan pertama dijadikan satu tagihan.

10. **Perjelas Alur Infaq dan SPP**

    * Dokumentasikan dan implementasikan alur pembayaran Infaq dan SPP secara jelas.
    * Hindari duplikasi atau ketidaksesuaian perhitungan.

11. **Infaq Belum Masuk Tagihan Siswa**

    * Perbaiki bug sehingga tagihan Infaq otomatis muncul pada tagihan siswa.

12. **Administrasi Bebas Belum Berfungsi**

    * Fitur "Administrasi Bebas" belum berjalan sesuai kebutuhan.
    * Lakukan pengecekan dan perbaikan proses generate tagihan serta pembayaran.

---

## Modul Tagihan dan Generate Data

13. **Cetak Otomatis Tagihan Terlalu Lama**

    * Optimalkan proses generate dan cetak tagihan otomatis.
    * Evaluasi query dan proses background yang menyebabkan keterlambatan.

14. **Proses Generate Sangat Lambat**

    * Investigasi penyebab performa lambat pada proses generate data/tagihan.
    * Targetkan peningkatan performa agar proses lebih cepat dan stabil.

---

## Antarmuka (UI/UX)

15. **Dropdown Disederhanakan**

    * Tampilan dropdown dibuat lebih sederhana seperti pada menu Data Master.
    * Kurangi kompleksitas pilihan agar lebih mudah digunakan oleh admin.
