<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Kwitansi Pembayaran' }}</title>
    <style>
        @page { margin: 24px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            color: #111827;
            line-height: 1.5;
        }
        .sheet {
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 22px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 18px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 14px;
        }
        .brand h1 {
            margin: 0;
            font-size: 18px;
        }
        .brand p,
        .meta p,
        .field p,
        .footer p {
            margin: 0;
        }
        .meta {
            text-align: right;
        }
        .badge {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 700;
            font-size: 11px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 16px;
        }
        .field {
            padding: 10px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }
        .field .label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 4px;
        }
        .field .value {
            font-weight: 700;
            color: #111827;
            word-break: break-word;
        }
        .amount {
            margin: 18px 0 14px;
            padding: 16px;
            background: #111827;
            color: #fff;
            border-radius: 14px;
        }
        .amount .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255,255,255,.72);
        }
        .amount .value {
            margin-top: 4px;
            font-size: 22px;
            font-weight: 800;
        }
        .note {
            margin-top: 18px;
            padding-top: 14px;
            border-top: 1px dashed #d1d5db;
            color: #4b5563;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            color: #6b7280;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <div class="brand">
                <h1>{{ $judul ?? 'Kwitansi Pembayaran' }}</h1>
                <p>{{ $subtitle ?? 'Dokumen resmi bukti transaksi pembayaran' }}</p>
                <div class="badge">{{ strtoupper((string) ($jenis ?? 'PAYMENT')) }}</div>
            </div>
            <div class="meta">
                <p><strong>No. Kwitansi:</strong> {{ $nomor_kwitansi ?? '-' }}</p>
                <p><strong>No. Invoice:</strong> {{ $nomor_invoice ?? '-' }}</p>
                <p><strong>Tanggal:</strong> {{ $tanggal ?? '-' }}</p>
            </div>
        </div>

        <div class="grid">
            <div class="field">
                <div class="label">Nama</div>
                <div class="value">{{ $nama ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="label">Nomor Induk / Pendaftaran</div>
                <div class="value">{{ $nomor_induk ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="label">Unit</div>
                <div class="value">{{ $unit ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="label">Kelas / Jalur</div>
                <div class="value">{{ $kelas ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="label">Metode Pembayaran</div>
                <div class="value">{{ $metode ?? '-' }}</div>
            </div>
            <div class="field">
                <div class="label">Status</div>
                <div class="value">{{ $status ?? '-' }}</div>
            </div>
        </div>

        <div class="amount">
            <div class="label">Jumlah Dibayar</div>
            <div class="value">{{ $nominal ?? '-' }}</div>
        </div>

        <div class="note">
            <strong>Keterangan</strong>
            <div>{{ $keterangan ?? 'Pembayaran telah diterima dan diverifikasi oleh petugas.' }}</div>
        </div>

        <div class="footer">
            <p>{{ $footer_left ?? 'Terima kasih atas pembayaran Anda.' }}</p>
            <p>{{ $footer_right ?? 'Dicetak otomatis dari sistem.' }}</p>
        </div>
    </div>
</body>
</html>