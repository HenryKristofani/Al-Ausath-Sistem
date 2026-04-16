# PROPOSAL TUGAS AKHIR 

## SISTEM INFORMASI ADMINISTRASI DAN AKADEMIK PONDOK PESANTREN AL-AUSATH BERBASIS WEB MODUL AKADEMIK E-RAPOR

**Pengusul**  
Henry Kristofani  
NIM. V3423041  

DIPLOMA TIGA TEKNIK INFORMATIKA  
UNIVERSITAS SEBELAS MARET  
SEKOLAH VOKASI  
2026  

---

## 1. JUDUL TUGAS AKHIR  
“Sistem Informasi Administrasi dan Akademik Pondok Pesantren Al-Ausath Berbasis Web Modul Akademik E-Rapor”

---

## 2. PERMASALAHAN  

Pondok Pesantren Al-Ausath memiliki jadwal kegiatan yang padat meliputi pembelajaran agama, tahfidz, bahasa Arab, dan kegiatan ekstrakurikuler untuk 425 santri. Namun, pengelolaan informasi jadwal kegiatan masih belum terintegrasi dalam satu sistem digital yang dapat diakses dengan mudah oleh seluruh santri. Akibatnya, informasi jadwal dan perubahan kegiatan belum dapat tersampaikan secara cepat dan merata kepada seluruh santri.  

Dalam hal penilaian akademik, proses pengolahan nilai santri masih dilakukan secara konvensional dan belum terdigitalisasi secara menyeluruh. Hal ini menyebabkan proses rekapitulasi nilai membutuhkan waktu yang relatif lama, berpotensi menimbulkan kesalahan pencatatan, serta menyulitkan pihak pesantren dalam melakukan pemantauan perkembangan akademik santri dari waktu ke waktu. Selain itu, wali santri harus datang langsung ke pesantren untuk memperoleh laporan hasil belajar, dan belum tersedia akses laporan akademik secara daring sebagai arsip digital.  

Diperlukan sebuah sistem informasi berbasis web yang mampu mengelola jadwal kegiatan secara real-time, memberikan notifikasi perubahan jadwal, serta sistem penilaian digital (e-rapor) yang memudahkan input nilai, kalkulasi otomatis, dan akses rapor online bagi wali santri.  

---

## 3. SOLUSI YANG DIUSULKAN  

Membangun Modul Penjadwalan dan E-Raport berbasis web yang terintegrasi dalam Sistem Informasi Pesantren. Modul ini akan mengelola pembuatan dan publikasi jadwal kegiatan dengan kalender interaktif, notifikasi otomatis untuk perubahan jadwal, serta sistem input nilai dan generate e-raport otomatis. Pengajar dapat input nilai langsung ke sistem, nilai akan terkalkulasi otomatis sesuai bobot (UTS, UAS, tugas, kehadiran), dan raport dapat diakses online oleh wali santri tanpa harus datang ke pesantren. Sistem juga menyediakan analisis perkembangan akademik santri dari waktu ke waktu.  

---

## 4. GAMBARAN UMUM PRODUK  

Berdasarkan Gambar 1, fokus Tugas Akhir saya ini ditunjukkan oleh warna magenta yang merepresentasikan modul Penjadwalan dan E-Raport sebagai komponen utama sistem akademik pesantren.  

Modul Penjadwalan dan E-Raport ini merupakan bagian dari Sistem Informasi Pesantren yang terintegrasi dengan modul administrasi (data santri) dan modul absensi (data kehadiran).  

### Fitur utama meliputi:

### A. Sub-Modul Penjadwalan:
- Master Jadwal: Pembuatan jadwal mingguan untuk semua kegiatan (pembelajaran, kajian, ekstrakulikuler)  
- Manajemen Perubahan Jadwal: Update jadwal dengan notifikasi otomatis  
- Export Jadwal: Download jadwal dalam format PDF  

### B. Sub-Modul Penilaian & E-Raport:
- Komponen Penilaian: Setting bobot nilai (misal UTS 30%, UAS 40%, Tugas 20%, Kehadiran 10%)  
- Input Nilai: Form input nilai per mata pelajaran oleh pengajar  
- Kalkulasi Otomatis: Perhitungan nilai akhir berdasarkan bobot  
- Generate E-Raport: Raport digital dengan grafik perkembangan  
- Raport Akhlak: Penilaian sikap dan perilaku santri  
- Cetak Raport: Download raport PDF untuk print  

### C. Dashboard & Analisis:
- Dashboard untuk pengurus: Overview nilai rata-rata per kelas  
- Dashboard untuk pengajar: Input nilai dan view statistik kelas yang diampu  
- Dashboard untuk santri: Lihat nilai dan raport  
- Analisis: Grafik perkembangan nilai per semester, identifikasi santri berprestasi/perlu bimbingan  

---

## 5. MANFAAT PRODUK  

### A. Bagi Pengajar:
- Input nilai lebih mudah dan cepat (langsung ke sistem)  
- Kalkulasi nilai otomatis, tidak perlu hitung manual  
- Bisa input nilai dari mana saja (tidak harus di kantor)  
- Rekap nilai tersedia real-time  
- Tidak perlu menulis catatan raport manual  

### B. Bagi Bagian Akademik:
- Proses pembuatan raport lebih cepat  
- Tidak perlu rekap manual dari form-form pengajar  
- Laporan akademik tersedia kapan saja  
- Mudah identifikasi santri berprestasi dan yang perlu bimbingan  
- Data untuk evaluasi kurikulum  

### C. Bagi Santri:
- Akses jadwal kegiatan real-time dari HP  
- Notifikasi jika ada perubahan jadwal  
- Bisa lihat nilai dan raport kapan saja  
- Tracking perkembangan akademik sendiri  
- Motivasi dengan melihat progress  

### D. Bagi Wali Santri:
- Monitor nilai anak secara real-time  
- Akses raport online tanpa harus ke pesantren  
- Bisa download dan print raport sendiri  
- Lihat perkembangan anak dari semester ke semester  
- Koordinasi dengan pengajar lebih mudah  

---

## 6. SPESIFIKASI PRODUK  

### a. Proses Bisnis  

(terdapat diagram pada dokumen asli)

---

### b. Kebutuhan Fungsional (Requirement Functional)

| RF-ID | Deskripsi |
|------|----------|
| RF-01 | Admin dan pengajar dapat melakukan login ke dalam sistem |
| RF-02 | Sistem membedakan hak akses pengguna berdasarkan peran (role) |
| RF-03 | Semua pengguna dapat melihat jadwal kegiatan pesantren |
| RF-04 | Santri dapat mengakses informasi akademik secara online |
| RF-04.1 | Santri dapat melihat jadwal kegiatan berdasarkan tanggal |
| RF-04.2 | Santri dapat melihat jadwal kegiatan berdasarkan jenis kegiatan |
| RF-04.3 | Santri dapat melihat detail jadwal kegiatan |
| RF-05 | Pengajar dapat mengelola nilai akademik santri |
| RF-05.1 | Pengajar dapat melihat daftar santri |
| RF-05.2 | Pengajar dapat menginput nilai |
| RF-05.3 | Pengajar dapat mengubah nilai |
| RF-05.4 | Sistem menghitung nilai akhir otomatis |
| RF-06 | Admin dapat mengelola data santri |
| RF-07 | Admin dapat mengelola data pengajar |
| RF-08 | Admin dapat mengelola jadwal kegiatan |
| RF-09 | Sistem menyimpan riwayat perubahan |
| RF-10 | Admin dapat melihat dashboard |
| RF-11 | Santri dapat mengunduh e-raport |
| RF-12 | Logout sistem |

---

## 7. TINJAUAN PRODUK SEJENIS  

(Tabel perbandingan dengan Siskesakti dan Google Classroom sesuai dokumen asli)

---

## 8. TOOL/TEORI YANG DIGUNAKAN  

### a) Tool  

**Hardware**
- Laptop  

**Software**
- Visual Studio Code  
- Laragon  
- Laravel  
- Typescript React  
- PostgreSQL  

### b) Teori  

- Sistem Informasi  
- Laravel  
- React  
- PostgreSQL  

---

## 9. ALOKASI WAKTU  

| No | Kegiatan | Jan | Feb | Mar | Apr | Mei |
|----|---------|-----|-----|-----|-----|-----|
| 1 | Analisis kebutuhan | | | | | |
| 2 | Perancangan sistem | | | | | |
| 3 | Implementasi sistem | | | | | |
| 4 | Penyelesaian sistem | | | | | |
| 5 | Dokumentasi & laporan | | | | | |

---

## 10. DAFTAR PUSTAKA  

Abramov, D., & Clark, A. (2020). React Documentation.  
Jogiyanto, H. M. (2005). Analisis dan Desain Sistem Informasi.  
Kyoreva, M. (2017). JavaScript Frameworks.  
Laudon, K., & Laudon, J. (2016). Management Information Systems.  
Momjian, B. (2015). PostgreSQL.  
Mulyadi. (2015). Laravel.  
Purnomo, D., & Hartanto, A. (2019). Laravel.  
Stonebraker, M., & Kemnitz, G. (1991). POSTGRES.  