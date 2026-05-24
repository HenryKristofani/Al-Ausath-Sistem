<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Kwitansi Pembayaran' }}</title>
    <style>
        @page {
            size: a5 portrait;
            margin: 18px 22px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5px;
            color: #111;
            line-height: 1.45;
        }

        /* ── Header ── */
        table.t-header { width: 100%; border-collapse: collapse; }
        .logo-cell { width: 56px; vertical-align: middle; }
        .logo-cell img { width: 50px; height: auto; }
        .brand-cell { vertical-align: middle; padding-left: 8px; }
        .brand-name {
            font-size: 12.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .brand-address { font-size: 7.8px; color: #444; margin-top: 1px; }

        /* ── Divider ── */
        .divider {
            border: none;
            border-top: 1.8px solid #111;
            margin: 8px 0 9px;
        }

        /* ── Title row ── */
        table.t-title { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
        .doc-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .doc-no { text-align: right; font-size: 9.5px; font-weight: bold; }

        /* ── Info grid ── */
        table.t-info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-half { width: 50%; vertical-align: top; }
        table.t-field { width: 100%; border-collapse: collapse; }
        .f-label { width: 78px; color: #555; padding-bottom: 3px; font-size: 9px; }
        .f-sep   { width: 8px;  color: #555; padding-bottom: 3px; font-size: 9px; }
        .f-val   { font-weight: bold; padding-bottom: 3px; font-size: 9px; }

        /* ── Items table ── */
        table.t-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .t-items th {
            background: #f0f0f0;
            border-top: 1px solid #bbb;
            border-bottom: 1px solid #bbb;
            padding: 4px 5px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #333;
        }
        .t-items td {
            padding: 6px 5px;
            font-size: 9px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }

        /* ── Rincian ── */
        .rincian-box { margin-bottom: 10px; }
        .rincian-label {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #333;
        }
        .rincian-val { color: #444; margin-top: 2px; font-size: 9px; }

        /* ── Footer / Summary ── */
        table.t-footer { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .col-summary { width: 55%; vertical-align: top; }
        .col-sig     { width: 45%; vertical-align: top; text-align: center; }

        table.t-summary { width: 100%; border-collapse: collapse; }
        .s-label { width: 100px; color: #555; padding-bottom: 3.5px; font-size: 9px; }
        .s-sep   { width: 8px;   color: #555; padding-bottom: 3.5px; font-size: 9px; }
        .s-val   { color: #111;  padding-bottom: 3.5px; font-size: 9px; }
        .s-bold  { font-weight: bold; color: #111; }

        /* signature */
        .sig-date { font-size: 9px; margin-bottom: 32px; }
        .sig-role { font-size: 9px; margin-bottom: 32px; }
        .sig-name { font-size: 9.5px; font-weight: bold; }
    </style>
</head>
<body>

{{-- ━━━━ HEADER ━━━━ --}}
<table class="t-header">
    <tr>
        <td class="logo-cell">
            @if(!empty($logo_base64))
                <img src="data:image/png;base64,{{ $logo_base64 }}" alt="Logo">
            @else
                <div style="width:50px;height:50px;background:#ddd;border-radius:4px;"></div>
            @endif
        </td>
        <td class="brand-cell">
            <div class="brand-name">Ma'had Al-Ausath Karanganyar</div>
            <div class="brand-address">Jl. Gotanon RT 04/03, Jati, Jaten, Karanganyar</div>
            <div class="brand-address">Telepon: 082148003054 &nbsp;|&nbsp; Email: sppalausath@gmail.com</div>
        </td>
    </tr>
</table>

<hr class="divider">

{{-- ━━━━ DOCUMENT TITLE ━━━━ --}}
<table class="t-title">
    <tr>
        <td class="doc-title">Kwitansi Pembayaran</td>
        <td class="doc-no">NO. {{ $nomor_kwitansi }}</td>
    </tr>
</table>

{{-- ━━━━ INFO GRID ━━━━ --}}
<table class="t-info">
    <tr>
        {{-- Left: nama & NIS --}}
        <td class="info-half" style="padding-right:6px;">
            <table class="t-field">
                <tr>
                    <td class="f-label">Nama Lengkap</td>
                    <td class="f-sep">:</td>
                    <td class="f-val">{{ $nama }}</td>
                </tr>
                <tr>
                    <td class="f-label">Nomor Induk</td>
                    <td class="f-sep">:</td>
                    <td class="f-val">{{ $nomor_induk }}</td>
                </tr>
            </table>
        </td>
        {{-- Right: kelas & unit --}}
        <td class="info-half" style="padding-left:6px;">
            <table class="t-field">
                <tr>
                    <td class="f-label">Kelas Sekarang</td>
                    <td class="f-sep">:</td>
                    <td class="f-val">{{ $kelas }}</td>
                </tr>
                <tr>
                    <td class="f-label">Unit Pendidikan</td>
                    <td class="f-sep">:</td>
                    <td class="f-val">{{ $unit }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- ━━━━ BILLING TABLE ━━━━ --}}
@php
    // Parse the date portion only (dd/mm/yyyy)
    $tanggal_only = strlen($tanggal) >= 10 ? substr($tanggal, 0, 10) : $tanggal;

    // Build period display
    $periode_display = '';
    if (!empty($bulan)) {
        $periode_display = $bulan;
    } elseif (!empty($periode)) {
        $periode_display = $periode;
    } elseif (!empty($jenis)) {
        $periode_display = $jenis;
    } else {
        $periode_display = '-';
    }
@endphp

<table class="t-items">
    <thead>
        <tr>
            <th style="text-align:center;width:20px;">No.</th>
            <th style="text-align:left;width:55px;">Tanggal</th>
            <th style="text-align:left;width:65px;">Periode</th>
            <th style="text-align:right;width:80px;">Biaya Tagihan</th>
            <th style="text-align:right;width:80px;">Pembayaran</th>
            <th style="text-align:right;width:80px;">Sisa Tagihan</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="text-align:center;">1</td>
            <td style="text-align:left;">{{ $tanggal_only }}</td>
            <td style="text-align:left;">{{ $periode_display }}</td>
            <td style="text-align:right;">{{ $nominal }}</td>
            <td style="text-align:right;font-weight:bold;">{{ $nominal }}</td>
            <td style="text-align:right;">{{ $sisa_tagihan ?? 'Rp 0' }}</td>
        </tr>
    </tbody>
</table>

{{-- ━━━━ RINCIAN ━━━━ --}}
<div class="rincian-box">
    <div class="rincian-label">Rincian :</div>
    <div class="rincian-val">{{ $rincian }}</div>
</div>

{{-- ━━━━ SUMMARY + SIGNATURE ━━━━ --}}
<table class="t-footer">
    <tr>
        {{-- Summary column --}}
        <td class="col-summary">
            <table class="t-summary">
                <tr>
                    <td class="s-label">Biaya Layanan</td>
                    <td class="s-sep">:</td>
                    <td class="s-val">Rp 0</td>
                </tr>
                <tr>
                    <td class="s-label s-bold">Total Pembayaran</td>
                    <td class="s-sep s-bold">:</td>
                    <td class="s-val s-bold">{{ $nominal }}</td>
                </tr>
                <tr>
                    <td class="s-label">Waktu Pembayaran</td>
                    <td class="s-sep">:</td>
                    <td class="s-val">{{ $tanggal }}</td>
                </tr>
                <tr>
                    <td class="s-label">Metode Pembayaran</td>
                    <td class="s-sep">:</td>
                    <td class="s-val">{{ $metode }}</td>
                </tr>
                <tr>
                    <td class="s-label">Waktu Cetak</td>
                    <td class="s-sep">:</td>
                    <td class="s-val">{{ now()->format('d/m/Y H:i') }} WIB</td>
                </tr>
            </table>
        </td>

        {{-- Signature column --}}
        <td class="col-sig">
            @php
                // Convert tanggal to readable Indonesian date for signature
                try {
                    $sigCarbon = \Illuminate\Support\Carbon::createFromFormat('d/m/Y H:i', $tanggal);
                } catch (\Exception $e) {
                    try {
                        $sigCarbon = \Illuminate\Support\Carbon::parse($tanggal);
                    } catch (\Exception $e2) {
                        $sigCarbon = now();
                    }
                }
                $monthsId = [
                    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
                    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
                    9=>'September',10=>'Oktober',11=>'November',12=>'Desember'
                ];
                $sigDateStr = $sigCarbon->format('d') . ' ' .
                              ($monthsId[(int)$sigCarbon->format('n')] ?? $sigCarbon->format('F')) . ' ' .
                              $sigCarbon->format('Y');
            @endphp

            <div class="sig-date">{{ $sigDateStr }}</div>
            <div class="sig-role">Petugas Penerima</div>
            <div class="sig-name">({{ $nama_petugas ?? 'Petugas Keuangan' }})</div>
        </td>
    </tr>
</table>

</body>
</html>