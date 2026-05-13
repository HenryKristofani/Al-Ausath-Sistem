<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Absensi Kelas</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h2 { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .meta-info { margin-bottom: 10px; font-size: 11px; }
    </style>
</head>
<body>
    <h2>Rekap Absensi Kelas</h2>

    <div class="meta-info">
        @if($request->query('tanggal_mulai') || $request->query('tanggal_selesai'))
            <p><strong>Periode:</strong> {{ $request->query('tanggal_mulai', '-') }} s/d {{ $request->query('tanggal_selesai', '-') }}</p>
        @endif
        <p><strong>Waktu Cetak:</strong> {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Kode Kelas</th>
                <th>Nama Kelas</th>
                <th class="text-center">Total Sesi</th>
                <th class="text-center">Santri Tercatat</th>
                <th class="text-center">Total Entri</th>
                <th class="text-center">Hadir</th>
                <th class="text-center">Izin</th>
                <th class="text-center">Sakit</th>
                <th class="text-center">Alfa</th>
                <th class="text-center">% Kehadiran</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $index => $row)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $row->kode_kelas }}</td>
                <td>{{ $row->nama_kelas }}</td>
                <td class="text-center">{{ $row->total_sesi }}</td>
                <td class="text-center">{{ $row->total_santri_tercatat }}</td>
                <td class="text-center">{{ $row->total_entri_absensi }}</td>
                <td class="text-center">{{ $row->jumlah_hadir }}</td>
                <td class="text-center">{{ $row->jumlah_izin }}</td>
                <td class="text-center">{{ $row->jumlah_sakit }}</td>
                <td class="text-center">{{ $row->jumlah_alfa }}</td>
                <td class="text-center">{{ $row->persentase_kehadiran }}%</td>
            </tr>
            @endforeach
            @if(count($data) === 0)
            <tr>
                <td colspan="11" class="text-center">Tidak ada data rekap.</td>
            </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
