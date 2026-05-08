# Rumus Nilai Rapor - Al-Ausath Sistem

Dokumentasi perumusan nilai di sistem Al-Ausath berdasarkan implementasi di `NilaiMapelController` dan `RaportPdfController`.

---

## 1. Nilai Mapel (Nilai Akhir Mapel)

### Input Komponen

```
tugas[]          : array of {nilai, jenis}
                   jenis: PR | TUGAS_PENGGANTI | MODUL_KOMPETENSI
                   (minimum 3 item)

ulangan[]        : array of {nilai, soal_disusun_pengajar, diawasi_pengajar}
                   (setelah filter: minimum 3 valid items)

ujian_akhir      : numeric [0-100]

bobot            : global per tahun_ajaran & semester
                   - bobot_harian (tugas)
                   - bobot_uts (ulangan)
                   - bobot_uas (ujian_akhir)
```

### Proses Perhitungan

#### Step 1: Hitung Rata-rata Tugas

```
nilai_tugas = SUM(tugas[].nilai) / COUNT(tugas[])
```

#### Step 2: Filter & Hitung Rata-rata Ulangan

Filter ulangan yang valid:

```
ulangan_valid[] = filter(ulangan[] where
                    soal_disusun_pengajar = true AND
                    diawasi_pengajar = true)

VALIDASI: COUNT(ulangan_valid) >= 3
```

Hitung rata-rata:

```
nilai_ulangan = SUM(ulangan_valid[].nilai) / COUNT(ulangan_valid)
```

#### Step 3: Hitung Nilai Akhir Raw

```
nilai_akhir_raw =
    (nilai_tugas × bobot_harian/100) +
    (nilai_ulangan × bobot_uts/100) +
    (ujian_akhir × bobot_uas/100)
```

#### Step 4: Round Half-Up (Presisi 2 Desimal)

```
nilai_akhir_mapel = FLOOR(nilai_akhir_raw × 100 + 0.5) / 100

Aturan: 1-4 turun, 5-9 naik
Contoh:
  79.144 → 79.14
  79.145 → 79.15
  79.155 → 79.16
```

#### Step 5: Resolve KKM & Status Ketuntasan

```
kkm = lookup(kode_mapel, kode_kelas, tahun_ajaran, semester)
      prefer kode_unit specific, fallback to global

status_ketuntasan = kkm.statusKetuntasan(nilai_akhir_mapel)
```

### Output Disimpan di `data_nilai_siswa`

| Field              | Value                                |
| ------------------ | ------------------------------------ |
| nilai_harian       | ROUND_HALFUP(nilai_tugas, 2)         |
| nilai_uts          | ROUND_HALFUP(nilai_ulangan, 2)       |
| nilai_uas          | ROUND_HALFUP(ujian_akhir, 2)         |
| nilai_akhir_mapel  | ROUND_HALFUP(nilai_akhir_raw, 2)     |
| nilai_rapor_tampil | (see step 1-2 di Nilai Rapor Tampil) |
| flag_warna_rapor   | 'MERAH' \| 'HITAM' (see step 1-2)    |
| status_ketuntasan  | derived from KKM                     |

---

## 2. Nilai Rapor Tampil (Display Grade)

### Input

```
nilai_akhir_mapel : dari perhitungan Step 1
```

### Proses Perhitungan

#### Step 1: Round to Integer

```
desimal = nilai_akhir_mapel - FLOOR(nilai_akhir_mapel)

IF desimal >= 0.5:
    nilai_rapor_bulat = CEILING(nilai_akhir_mapel)
ELSE:
    nilai_rapor_bulat = FLOOR(nilai_akhir_mapel)
```

#### Step 2: Normalize & Determine Flag Warna

```
IF nilai_rapor_bulat > 98:
    nilai_rapor_bulat = 98

IF nilai_akhir_mapel < 50 OR nilai_rapor_bulat < 50:
    nilai_rapor_tampil = 50
    flag_warna_rapor = 'MERAH'
ELSE:
    nilai_rapor_tampil = nilai_rapor_bulat
    flag_warna_rapor = 'HITAM'
```

**Catatan:**

- Warna tinta mengikuti nilai **asli/mentah** (nilai_akhir_mapel), bukan yang sudah dibulatkan
- Nilai minimum pada rapor adalah **50**, diinterpretasikan sebagai nilai tidak tuntas
- Nilai maksimal pada rapor adalah **98**

### Output

| Field              | Value                |
| ------------------ | -------------------- |
| nilai_rapor_tampil | 50-98                |
| flag_warna_rapor   | 'MERAH' atau 'HITAM' |

---

## 3. Rata-rata Nilai Rapor (Per Santri)

### Input

```
Semua nilai_rapor_tampil milik santri untuk tahun_ajaran & semester tertentu
```

### Proses Perhitungan

```
jumlah_nilai = SUM(nilai_rapor_tampil[] per mapel)
               ROUND(result, 2)

jumlah_mapel = COUNT(mapel yang memiliki nilai)

rata_rata_nilai = IF jumlah_mapel > 0:
                      ROUND(jumlah_nilai / jumlah_mapel, 2)
                  ELSE:
                      0.0
```

### Output Disimpan di `data_raport`

| Field             | Value                       |
| ----------------- | --------------------------- |
| jumlah_nilai      | SUM(nilai_rapor_tampil)     |
| rata_rata         | jumlah_nilai / jumlah_mapel |
| peringkat_kelas   | (computed separately)       |
| total_siswa_kelas | (dari kelas)                |

---

## 4. Rounding Methods Implementation

### roundHalfUp (General, Presisi = 2)

```php
private function roundHalfUp(float $value, int $precision): float
{
    $factor = 10 ** $precision;
    return floor(($value * $factor) + 0.5) / $factor;
}
```

**Hasil:** Pembulatan ke atas jika desimal >= 5

### roundRaporInteger (Untuk Nilai Rapor Bulat)

```php
private function roundRaporInteger(float $nilai): int
{
    $desimal = $nilai - floor($nilai);
    return $desimal >= 0.5 ? (int) ceil($nilai) : (int) floor($nilai);
}
```

**Hasil:** Integer (bulat tanpa desimal)

---

## 5. Validasi & Constraint

### Nilai Mapel

- ✅ nomor_induk must exist di data_santri
- ✅ kode_mapel must exist di data_mata_pelajaran
- ✅ kode_kelas must exist di data_kelas
- ✅ kode_kelas must match santri's actual class
- ✅ Minimum 3 tugas item
- ✅ Minimum 3 ulangan valid items (soal_disusun_pengajar & diawasi_pengajar)
- ✅ ujian_akhir dalam range [0-100]
- ✅ Bobot harus sudah dikonfigurasi untuk tahun_ajaran & semester

### Nilai Rapor Tampil

- Range: 50-98 (dengan special case jika < 50 maka 50)
- Flag warna: 'MERAH' atau 'HITAM'

---

## 6. Konteks Database

### Tabel: data_nilai_siswa

```sql
id_nilai (PK)
nomor_induk (FK)
kode_mapel (FK)
kode_kelas (FK)
tahun_ajaran
semester
nilai_harian (decimal 5,2)
nilai_uts (decimal 5,2)
nilai_uas (decimal 5,2)
nilai_akhir_mapel (decimal 5,2)
nilai_rapor_tampil (int)
flag_warna_rapor (MERAH|HITAM)
status_ketuntasan (T|TT or enum)
```

### Tabel: data_raport

```sql
id_raport (PK)
nomor_induk (FK)
kode_kelas (FK)
tahun_ajaran
semester
jumlah_nilai (decimal 8,2)
rata_rata (decimal 5,2)
peringkat_kelas
total_siswa_kelas
hadir, sakit, izin, alpha (int)
keseharian_kebersihan, kerapian, keterampilan (int/char)
status_raport (DRAFT|FINAL|ARCHIVED)
```

### Tabel: data_bobot_nilai

```sql
id_bobot (PK)
tahun_ajaran
semester
bobot_harian (decimal 5,2) [tugas]
bobot_uts (decimal 5,2)    [ulangan]
bobot_uas (decimal 5,2)    [ujian_akhir]
kode_unit (nullable, null = global)
```

### Tabel: data_kkm_mapel

```sql
id_kkm (PK)
kode_mapel (FK)
tahun_ajaran
semester
nilai_kkm (decimal 5,2)
kode_unit (nullable, prefer specific unit)
```

---

## 7. Code References

| Fungsi                | File                     | Line     |
| --------------------- | ------------------------ | -------- |
| upsert (main formula) | NilaiMapelController.php | ~121     |
| averageComponent      | NilaiMapelController.php | ~229     |
| filterValidUlangan    | NilaiMapelController.php | ~242     |
| roundHalfUp           | NilaiMapelController.php | ~254     |
| roundRaporInteger     | NilaiMapelController.php | ~262     |
| normalizeNilaiRapor   | NilaiMapelController.php | ~269     |
| buildRaportPayload    | RaportPdfController.php  | ~130     |
| Perhitungan rata-rata | RaportPdfController.php  | ~162-167 |

---

## 8. Flowchart Proses

```
INPUT: tugas[], ulangan[], ujian_akhir, bobot

├─ [HARIAN] → averageComponent(tugas[]) → nilai_tugas
├─ [ULANGAN] → filterValidUlangan(ulangan[])
│             → averageComponent(ulangan_valid[]) → nilai_ulangan
├─ [AKHIR] → nilai_ujian_akhir = ujian_akhir
│
├─ FORMULA:
│  nilai_akhir_raw = (nilai_tugas × bobot_harian/100) +
│                    (nilai_ulangan × bobot_uts/100) +
│                    (ujian_akhir × bobot_uas/100)
│
├─ ROUND: roundHalfUp(nilai_akhir_raw, 2) → nilai_akhir_mapel
│
├─ RAPORT TAMPIL:
│  ├─ roundRaporInteger(nilai_akhir_mapel) → nilai_rapor_bulat
│  ├─ normalizeNilaiRapor() → nilai_rapor_tampil, flag_warna_rapor
│  └─ OUTPUT: nilai_rapor_tampil (50-98), flag_warna_rapor (MERAH|HITAM)
│
├─ KKM:
│  └─ resolveKkm() → status_ketuntasan
│
└─ SAVE: data_nilai_siswa record
```

---

## Catatan Penting

1. **Bobot Global**: Bobot nilai dikonfigurasi **global per semester**, bukan per unit/kelas
2. **Ulangan Valid**: Hanya ulangan dengan `soal_disusun_pengajar = true` AND `diawasi_pengajar = true` yang dihitung
3. **Nilai Minimum Rapor**: Jika hasil perhitungan < 50, ditampilkan sebagai 50 (flag MERAH)
4. **Nilai Maksimal Rapor**: Dibatasi maksimal 98
5. **Rounding Strategy**: Half-up method (desimal 0.5+ naik)
6. **Warna Tinta**: Mengikuti nilai **asli/mentah**, bukan nilai yang sudah dibulatkan
