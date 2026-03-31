# GRADING_SYSTEM_SPEC.md
# Spesifikasi Sistem Penilaian — MTQ Al Ausath
> Gunakan dokumen ini sebagai referensi tunggal saat mengimplementasikan atau mengubah logika perhitungan nilai rapor.

---

## 1. Struktur Input

```json
{
  "tugas":      [{ "nilai": number }],   // minimal 3 item
  "ulangan":    [{ "nilai": number }],   // minimal 3 item
  "ujian_akhir": number
}
```

---

## 2. Bobot Komponen

| Komponen    | Bobot |
|-------------|-------|
| Tugas       | 20%   |
| Ulangan     | 30%   |
| Ujian Akhir | 50%   |

---

## 3. Langkah Perhitungan Nilai Akhir

### Step 1 — Rata-rata Tugas
```
rata_tugas = sum(tugas[i].nilai) / len(tugas)
```
- Gunakan semua item tugas yang ada (minimal 3).
- Jangan bulatkan di tahap ini, simpan sebagai float penuh.

### Step 2 — Rata-rata Ulangan
```
rata_ulangan = sum(ulangan[i].nilai) / len(ulangan)
```
- Gunakan semua item ulangan yang ada (minimal 3).
- Jangan bulatkan di tahap ini, simpan sebagai float penuh.

### Step 3 — Hitung Nilai Akhir Mentah
```
nilai_akhir_mentah = (rata_tugas * 0.20)
                   + (rata_ulangan * 0.30)
                   + (ujian_akhir * 0.50)
```

### Step 4 — Pembulatan Nilai Rapor (per Mata Pelajaran)
Aturan pembulatan ke bilangan **bulat**:
```
- Desimal 0.1 – 0.4  → buang (floor)
- Desimal 0.5 – 0.9  → naik  (ceil)
```
> ⚠️ Ini BUKAN `round()` standar. `round(92.5)` di Python = 92 (banker's rounding).
> Gunakan logika eksplisit:
```python
import math
def bulatkan_rapor(nilai: float) -> int:
    desimal = nilai - math.floor(nilai)
    if desimal >= 0.5:
        return math.ceil(nilai)
    else:
        return math.floor(nilai)
```

### Step 5 — Batas Nilai Rapor
Terapkan SETELAH pembulatan:
```
if nilai_rapor > 98:
    nilai_rapor = 98

if nilai_rapor < 50:
    nilai_rapor = 50
    flag = "MERAH"       # tulis dengan tinta merah
else:
    flag = "HITAM"       # tulis dengan tinta hitam
```
> Nilai 50 ASLI (sebelum clamp) dan nilai 50 hasil clamp sama-sama ditulis 50,
> tapi dibedakan lewat flag warna.

---

## 4. Struktur Output Nilai Rapor

```json
{
  "rata_tugas":        85.0,
  "rata_ulangan":      82.666...,
  "nilai_akhir_mentah": 87.3,
  "nilai_rapor":       87,
  "flag_warna":        "HITAM"
}
```

---

## 5. Perhitungan Rata-Rata Rapor (untuk Peringkat Kelas)

Rata-rata rapor digunakan untuk menentukan peringkat, BUKAN nilai akhir per mapel.

```
rata_rapor = ( (nilai_hifzh * 2)
             + (rata_nilai_diniyyah * 2)
             + (rata_nilai_umum * 1) ) / 5
```

### Pembulatan Rata-Rata Rapor
Dibulatkan ke **2 desimal** dengan aturan yang sama:
```
- Desimal ke-3: 1–4 → buang
- Desimal ke-3: 5–9 → naik
```
```python
def bulatkan_rata_rapor(nilai: float) -> float:
    faktor = 100
    shifted = nilai * faktor
    desimal = shifted - math.floor(shifted)
    if desimal >= 0.5:
        return math.ceil(shifted) / faktor
    else:
        return math.floor(shifted) / faktor
```

Contoh:
```
87.666... → 87.67   ✅
76.233... → 76.23   ✅
```

---

## 6. Peringkat Kelas

```
- Kelas besar  → tampilkan 10 besar
- Kelas kecil  → tampilkan 5 besar
```
Urutan: rata_rapor DESC. Jika sama, urutkan berdasarkan nilai_hifzh DESC.

---

## 7. Validasi Input

| Field          | Aturan                                              |
|----------------|-----------------------------------------------------|
| tugas          | Minimal 3 item, nilai 0–100                         |
| ulangan        | Minimal 3 item, nilai 0–100                         |
| ujian_akhir    | Satu nilai, 0–100                                   |
| soal_disusun_pengajar | Harus `true` agar ulangan dihitung          |
| diawasi_pengajar      | Harus `true` agar ulangan dihitung          |

> Jika `soal_disusun_pengajar` atau `diawasi_pengajar` = `false`,
> item ulangan tersebut **tidak boleh dimasukkan** ke perhitungan rata-rata.

---

## 8. Contoh Implementasi Lengkap (PHP)

```php
<?php

/**
 * Pembulatan nilai rapor per mata pelajaran → bilangan bulat.
 * Aturan: desimal 0.1–0.4 dibuang, 0.5–0.9 naik.
 *
 * ⚠️ JANGAN pakai round() bawaan PHP — round(92.5) = 93 di PHP memang benar,
 *    tapi round(92.45) bisa meleset karena floating point error.
 *    Gunakan fungsi ini agar konsisten.
 */
function bulatkanRapor(float $nilai): int
{
    $desimal = $nilai - floor($nilai);
    return $desimal >= 0.5 ? (int) ceil($nilai) : (int) floor($nilai);
}

/**
 * Pembulatan rata-rata rapor → 2 desimal.
 * Aturan: digit ke-3 di belakang koma 1–4 dibuang, 5–9 naik.
 */
function bulatkanRataRapor(float $nilai): float
{
    $shifted  = $nilai * 100;
    $desimal  = $shifted - floor($shifted);
    $result   = $desimal >= 0.5 ? ceil($shifted) : floor($shifted);
    return $result / 100;
}

/**
 * Hitung nilai akhir rapor satu mata pelajaran.
 *
 * @param  array<float>  $tugas      Minimal 3 nilai tugas yang valid
 * @param  array<float>  $ulangan    Minimal 3 nilai ulangan yang valid
 * @param  float         $ujianAkhir Nilai ujian akhir semester
 * @return array{
 *   rata_tugas: float,
 *   rata_ulangan: float,
 *   nilai_akhir_mentah: float,
 *   nilai_rapor: int,
 *   flag_warna: string
 * }
 */
function hitungNilaiRapor(array $tugas, array $ulangan, float $ujianAkhir): array
{
    if (count($tugas) < 3) {
        throw new \InvalidArgumentException('Minimal 3 nilai tugas.');
    }
    if (count($ulangan) < 3) {
        throw new \InvalidArgumentException('Minimal 3 nilai ulangan.');
    }

    $rataTugas   = array_sum($tugas)   / count($tugas);
    $rataUlangan = array_sum($ulangan) / count($ulangan);

    $nilaiMentah = ($rataTugas * 0.20)
                 + ($rataUlangan * 0.30)
                 + ($ujianAkhir * 0.50);

    $nilaiRapor = bulatkanRapor($nilaiMentah);

    // Batas atas
    if ($nilaiRapor > 98) {
        $nilaiRapor = 98;
    }

    // Batas bawah + flag warna
    if ($nilaiRapor < 50) {
        $nilaiRapor = 50;
        $flagWarna  = 'MERAH';
    } else {
        $flagWarna  = 'HITAM';
    }

    return [
        'rata_tugas'          => $rataTugas,
        'rata_ulangan'        => $rataUlangan,
        'nilai_akhir_mentah'  => $nilaiMentah,
        'nilai_rapor'         => $nilaiRapor,
        'flag_warna'          => $flagWarna,
    ];
}

/**
 * Filter ulangan: hanya hitung yang soal_disusun_pengajar = true
 * DAN diawasi_pengajar = true.
 *
 * @param  array<array{nilai: float, soal_disusun_pengajar: bool, diawasi_pengajar: bool}>  $ulangan
 * @return array<float>
 */
function filterUlangan(array $ulangan): array
{
    return array_values(
        array_map(
            fn($u) => (float) $u['nilai'],
            array_filter(
                $ulangan,
                fn($u) => $u['soal_disusun_pengajar'] === true
                       && $u['diawasi_pengajar']       === true
            )
        )
    );
}

// ── Contoh dari data santri ──────────────────────────────────────────────────

$input = [
    'tugas' => [
        ['nilai' => 80, 'jenis' => 'PR'],
        ['nilai' => 85, 'jenis' => 'TUGAS_PENGGANTI'],
        ['nilai' => 90, 'jenis' => 'MODUL_KOMPETENSI'],
    ],
    'ulangan' => [
        ['nilai' => 78, 'soal_disusun_pengajar' => true, 'diawasi_pengajar' => true],
        ['nilai' => 82, 'soal_disusun_pengajar' => true, 'diawasi_pengajar' => true],
        ['nilai' => 88, 'soal_disusun_pengajar' => true, 'diawasi_pengajar' => true],
    ],
    'ujian_akhir' => 91,
];

$nilaiTugas   = array_column($input['tugas'], 'nilai');
$nilaiUlangan = filterUlangan($input['ulangan']);

$hasil = hitungNilaiRapor($nilaiTugas, $nilaiUlangan, (float) $input['ujian_akhir']);

// $hasil['nilai_rapor'] = 87
// $hasil['flag_warna']  = 'HITAM'
```

### Rata-Rata Rapor untuk Peringkat Kelas

```php
function hitungRataRapor(
    float $nilaiHifzh,
    float $rataDiniyyah,
    float $rataUmum
): float {
    $mentah = (($nilaiHifzh * 2) + ($rataDiniyyah * 2) + ($rataUmum * 1)) / 5;
    return bulatkanRataRapor($mentah);
}
```

---

## 9. Catatan Penting untuk AI Agent

1. **Hati-hati dengan `round()` bawaan PHP** — bisa meleset pada float tertentu akibat floating point precision (misal `round(87.35, 0)` bisa jadi 87). Gunakan fungsi `bulatkanRapor()` di atas agar konsisten.
2. **Nilai 100 tidak pernah ditulis di rapor** — selalu di-clamp ke 98.
3. **Nilai di bawah 50 tetap ditulis 50** tapi diberi penanda merah — jangan hapus nilainya, simpan `flag_warna = "MERAH"`.
4. **Rata-rata tugas dan ulangan** dihitung dari semua item valid, bukan hanya 3 item pertama.
5. **Filter ulangan** sebelum menghitung rata-rata: gunakan `filterUlangan()`, hanya item dengan `soal_disusun_pengajar = true` AND `diawasi_pengajar = true`.
6. **Rata-rata rapor** (untuk peringkat) menggunakan rumus bobot hifzh/diniyyah/umum via `hitungRataRapor()` — berbeda dari nilai akhir per mapel.
