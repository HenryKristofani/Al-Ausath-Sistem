<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Perkembangan Anak Didik</title>
    <style>
        @page {
            margin: 14mm 12mm 16mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #111;
            line-height: 1.25;
        }

        .page {
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 42%;
            left: 8%;
            width: 80%;
            text-align: center;
            font-size: 86px;
            color: rgba(200, 0, 0, 0.10);
            transform: rotate(-25deg);
            z-index: -1;
            font-weight: bold;
            letter-spacing: 8px;
        }

        .title {
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            border-bottom: 2px solid #111;
            padding-bottom: 3px;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #222;
            padding: 5px 6px;
            vertical-align: top;
        }

        th {
            background: #f5f5f5;
        }

        .header-table td {
            border: 0;
            padding: 1px 0;
        }

        .header-table .label {
            width: 32%;
            font-weight: bold;
        }

        .header-table .colon {
            width: 4%;
            text-align: center;
            font-weight: bold;
        }

        .header-table .value {
            width: 64%;
        }

        .meta-wrap {
            margin-bottom: 12px;
        }

        .subject-table {
            margin-top: 8px;
        }

        .subject-table th,
        .subject-table td {
            text-align: center;
            vertical-align: middle;
        }

        .subject-table td.subject-name,
        .subject-table td.subject-note {
            text-align: left;
        }

        .subject-table td.blank {
            color: transparent;
        }

        .summary-table td,
        .summary-table th {
            vertical-align: middle;
        }

        .summary-label {
            text-align: center;
            font-weight: bold;
        }

        .signature-wrap {
            margin-top: 22px;
            width: 100%;
        }

        .signature-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .signature-title {
            text-align: center;
            margin-bottom: 48px;
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

        .signature-line {
            display: inline-block;
            width: 76%;
            border-bottom: 1px solid #111;
            margin-top: 30px;
        }

        .small-note {
            font-size: 9.5px;
        }
    </style>
</head>

<body>
    <div class="page">
        @if (strtoupper((string) $raport->status_raport) === 'DRAFT')
        <div class="watermark">DRAFT</div>
        @endif

        @php
            $namaSekolah = $unit->nama_unit ?? 'Ma\'had Al Ausath';
            $tingkat = $kelas->nama_kelas ?? $raport->kode_kelas;
            $jumlahNilaiDisplay = number_format((float) $jumlahNilai, 2, ',', '.');
            $rataRataDisplay = number_format((float) $rataRataNilai, 2, ',', '.');
            $peringkatKelas = $raport->peringkat_kelas ?: '-';
            $totalSiswaKelas = $raport->total_siswa_kelas ?: '-';
            $tanggalTerbit = $raport->tanggal_terbit
                ? \Carbon\Carbon::parse($raport->tanggal_terbit)->translatedFormat('d F Y')
                : '-';
            $waliKelasNama = $waliKelas->nama_lengkap ?? '-';
        @endphp

        <div class="title">LAPORAN PERKEMBANGAN ANAK DIDIK</div>

        <div class="meta-wrap">
            <table class="header-table">
                <tr>
                    <td class="label">Nama Anak Didik</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $santri->nama_lengkap_santri }}</td>
                    <td class="label">Tingkat</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $tingkat }}</td>
                </tr>
                <tr>
                    <td class="label">Nomor Induk</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $santri->nomor_induk }}</td>
                    <td class="label">Semester</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $raport->semester }} ({{ $raport->semester == 1 ? 'Satu' : 'Dua' }})</td>
                </tr>
                <tr>
                    <td class="label">Nama Sekolah</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $namaSekolah }}</td>
                    <td class="label">Tahun Pelajaran</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $raport->tahun_ajaran }}</td>
                </tr>
            </table>
        </div>

        <table class="subject-table">
            <thead>
                <tr>
                    <th rowspan="2" width="6%">No</th>
                    <th rowspan="2" width="29%">Mata Pelajaran</th>
                    <th colspan="2" width="20%">Nilai</th>
                    <th rowspan="2" width="45%">Keterangan</th>
                </tr>
                <tr>
                    <th width="10%">Angka</th>
                    <th width="10%">Huruf</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($nilaiMapel as $index => $row)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="subject-name">{{ $row->nama_mapel ?? $row->kode_mapel }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) ($row->nilai_rapor_tampil ?? 0), 2, ',', '.'), '0'), ',') }}</td>
                        <td>{{ $row->nilai_huruf ?? '' }}</td>
                        <td class="subject-note">{{ $row->keterangan_mapel ?: ($row->predikat ?? '') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Belum ada data nilai.</td>
                    </tr>
                @endforelse
                <tr>
                    <td colspan="2" class="summary-label">Jumlah Nilai</td>
                    <td class="text-center">{{ $jumlahNilaiDisplay }}</td>
                    <td class="blank">-</td>
                    <td class="blank">-</td>
                </tr>
                <tr>
                    <td colspan="2" class="summary-label">Nilai Rata-Rata</td>
                    <td class="text-center">{{ $rataRataDisplay }}</td>
                    <td class="blank">-</td>
                    <td class="blank">-</td>
                </tr>
                <tr>
                    <td colspan="5" class="text-left">
                        Peringkat Kelas Ke: {{ $peringkatKelas }} dari {{ $totalSiswaKelas }} Siswa
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="signature-wrap">
            <table class="signature-table">
                <tr>
                    <td width="50%" class="text-center">
                        <div>Mengetahui</div>
                        <div>Orang Tua / Wali</div>
                        <div class="signature-line"></div>
                    </td>
                    <td width="50%" class="text-center">
                        <div>Diberikan pada {{ $tanggalTerbit }}</div>
                        <div>Wali Kelas</div>
                        <div class="signature-line"></div>
                        <div style="margin-top: 10px;">{{ $waliKelasNama }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>