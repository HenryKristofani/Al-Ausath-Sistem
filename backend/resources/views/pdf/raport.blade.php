<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Rapor Santri</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
        }

        .page {
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 45%;
            left: 10%;
            width: 80%;
            text-align: center;
            font-size: 92px;
            color: rgba(200, 0, 0, 0.10);
            transform: rotate(-25deg);
            z-index: -1;
            font-weight: bold;
            letter-spacing: 8px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            margin-bottom: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #222;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #efefef;
        }

        .section {
            margin-top: 14px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .meta td {
            border: 1px solid #666;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .red {
            color: #b00000;
            font-weight: bold;
        }

        .footer-note {
            margin-top: 14px;
            font-size: 11px;
            color: #444;
        }
    </style>
</head>

<body>
    <div class="page">
        @if(strtoupper((string) $raport->status_raport) === 'DRAFT')
        <div class="watermark">DRAFT</div>
        @endif

        <div class="title">LAPORAN HASIL BELAJAR SANTRI</div>
        <div class="subtitle">Tahun Ajaran {{ $raport->tahun_ajaran }} - Semester {{ $raport->semester }}</div>

        <table class="meta">
            <tr>
                <td width="20%"><strong>Nomor Induk</strong></td>
                <td width="30%">{{ $santri->nomor_induk }}</td>
                <td width="20%"><strong>Kelas</strong></td>
                <td width="30%">{{ $raport->kode_kelas }}</td>
            </tr>
            <tr>
                <td><strong>Nama Santri</strong></td>
                <td>{{ $santri->nama_lengkap_santri }}</td>
                <td><strong>Status Rapor</strong></td>
                <td>{{ $raport->status_raport }}</td>
            </tr>
        </table>

        <div class="section">A. Nilai Mata Pelajaran</div>
        <table>
            <thead>
                <tr>
                    <th width="6%">No</th>
                    <th width="28%">Mata Pelajaran</th>
                    <th width="10%">Harian</th>
                    <th width="10%">Ulangan</th>
                    <th width="10%">Ujian</th>
                    <th width="12%">Akhir</th>
                    <th width="12%">Nilai Rapor</th>
                    <th width="12%">Warna</th>
                </tr>
            </thead>
            <tbody>
                @forelse($nilaiMapel as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $row->nama_mapel ?? $row->kode_mapel }}</td>
                    <td class="text-center">{{ $row->nilai_harian }}</td>
                    <td class="text-center">{{ $row->nilai_uts }}</td>
                    <td class="text-center">{{ $row->nilai_uas }}</td>
                    <td class="text-center">{{ $row->nilai_akhir_mapel }}</td>
                    <td class="text-center {{ strtoupper((string) $row->flag_warna_rapor) === 'MERAH' ? 'red' : '' }}">{{ $row->nilai_rapor_tampil }}</td>
                    <td class="text-center">{{ $row->flag_warna_rapor }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center">Belum ada data nilai mapel.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="section">B. Kehadiran</div>
        <table>
            <tr>
                <th>Hadir</th>
                <th>Sakit</th>
                <th>Izin</th>
                <th>Tanpa Keterangan</th>
            </tr>
            <tr>
                <td class="text-center">{{ $raport->hadir }}</td>
                <td class="text-center">{{ $raport->sakit }}</td>
                <td class="text-center">{{ $raport->izin }}</td>
                <td class="text-center">{{ $raport->alpha }}</td>
            </tr>
        </table>

        <div class="section">C. Akhlak dan Catatan</div>
        <table>
            <tr>
                <th width="30%">Aspek Akhlak</th>
                <th width="10%">Nilai</th>
                <th width="60%">Deskripsi</th>
            </tr>
            @forelse($nilaiAkhlak as $akhlak)
            <tr>
                <td>{{ $akhlak->aspek }}</td>
                <td class="text-center">{{ $akhlak->nilai_angka }}</td>
                <td>{{ $akhlak->deskripsi }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="3" class="text-center">Belum ada data nilai akhlak.</td>
            </tr>
            @endforelse
        </table>

        <table style="margin-top: 10px;">
            <tr>
                <td width="35%"><strong>Keseharian - Kebersihan</strong></td>
                <td width="15%" class="text-center">{{ $raport->keseharian_kebersihan }}</td>
                <td width="35%"><strong>Keseharian - Kerapian</strong></td>
                <td width="15%" class="text-center">{{ $raport->keseharian_kerapian }}</td>
            </tr>
            <tr>
                <td><strong>Keseharian - Keterampilan</strong></td>
                <td class="text-center">{{ $raport->keseharian_keterampilan }}</td>
                <td><strong>Rata-rata Rapor</strong></td>
                <td class="text-center">{{ $raport->rata_rata }}</td>
            </tr>
        </table>

        <div class="section">D. Catatan Wali Kelas</div>
        <table>
            <tr>
                <td>{{ $raport->catatan_wali ?: '-' }}</td>
            </tr>
        </table>

        <div class="footer-note">
            Tanggal terbit: {{ $raport->tanggal_terbit ?: '-' }}
        </div>
    </div>
</body>

</html>