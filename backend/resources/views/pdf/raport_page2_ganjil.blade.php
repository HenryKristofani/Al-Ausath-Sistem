<div style="page-break-before: always;"></div>

<div class="page">
    @if (strtoupper((string) $raport->status_raport) === 'DRAFT')
    <div class="watermark">DRAFT</div>
    @endif

    <div style="margin-top: 20px;">
        <!-- B. EKSTRA KULIKULER -->
        <div style="font-weight: bold; margin-bottom: 5px;">B. EKSTRA KULIKULER</div>
        <table class="subject-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th width="8%">No</th>
                    <th width="62%">Kegiatan</th>
                    <th width="30%">Predikat</th>
                </tr>
            </thead>
            <tbody>
                @php
                $ekskul = is_array($raport->ekstrakurikuler) ? $raport->ekstrakurikuler : [];
                $ekskulCount = count($ekskul);
                $minRows = 4;
                @endphp

                @for ($i = 0; $i < max($ekskulCount, $minRows); $i++)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="text-left" style="padding-left: 10px;">{{ isset($ekskul[$i]['nama']) ? $ekskul[$i]['nama'] : '' }}</td>
                    <td>{{ isset($ekskul[$i]['nilai']) ? $ekskul[$i]['nilai'] : '' }}</td>
                </tr>
                @endfor
            </tbody>
        </table>

        <!-- C. ASPEK KEPRIBADIAN -->
        <div style="font-weight: bold; margin-bottom: 5px;">C. ASPEK KEPRIBADIAN</div>
        <table class="subject-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th width="8%">No</th>
                    <th width="62%">Kepribadian</th>
                    <th width="30%">Predikat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td class="text-left" style="padding-left: 10px;">Kelakuan</td>
                    <td>{{ $raport->keseharian_kelakuan ?? '' }}</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td class="text-left" style="padding-left: 10px;">Kerajinan</td>
                    <td>{{ $raport->keseharian_kerajinan ?? '' }}</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td class="text-left" style="padding-left: 10px;">Kerapian</td>
                    <td>{{ $raport->keseharian_kerapian ?? '' }}</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td class="text-left" style="padding-left: 10px;">Kebersihan</td>
                    <td>{{ $raport->keseharian_kebersihan ?? '' }}</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td class="text-left" style="padding-left: 10px;">Kedisiplinan</td>
                    <td>{{ $raport->keseharian_kedisiplinan ?? '' }}</td>
                </tr>
                <tr>
                    <td>6</td>
                    <td class="text-left" style="padding-left: 10px;">Ketaatan</td>
                    <td>{{ $raport->keseharian_ketaatan ?? '' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- D. KETIDAKHADIRAN -->
        <div style="font-weight: bold; margin-bottom: 5px;">D. KETIDAKHADIRAN</div>
        <table class="subject-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th width="8%">No</th>
                    <th width="62%">Keterangan</th>
                    <th width="30%">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td class="text-left" style="padding-left: 10px;">Sakit</td>
                    <td>{{ $raport->sakit ?? 0 }} hari</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td class="text-left" style="padding-left: 10px;">Izin</td>
                    <td>{{ $raport->izin ?? 0 }} hari</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td class="text-left" style="padding-left: 10px;">Tanpa Keterangan</td>
                    <td>{{ $raport->alpha ?? 0 }} hari</td>
                </tr>
            </tbody>
        </table>

        <!-- E. CATATAN TENTANG PENGEMBANGAN DIRI -->
        <div style="font-weight: bold; margin-bottom: 5px;">E. CATATAN TENTANG PENGEMBANGAN DIRI</div>
        <div style="border: 1px solid #222; padding: 10px; min-height: 80px; margin-bottom: 30px; text-align: left;">
            {!! nl2br(e($raport->catatan_wali ?? '')) !!}
        </div>

        <div class="signature-wrap" style="margin-top: 40px;">
            <table class="signature-table">
                <tr>
                    <td width="50%" class="text-center">
                        <div>Mengetahui</div>
                        <div>Orang Tua / Wali</div>
                        <div class="signature-line" style="margin-top: 50px;"></div>
                    </td>
                    <td width="50%" class="text-center">
                        <div>Diberikan di Karanganyar, {{ $tanggalTerbit }}</div>
                        <div style="margin-top: 10px;">Wali Kelas</div>
                        <div class="signature-line" style="margin-top: 50px;"></div>
                        <div style="margin-top: 10px;">( {{ $waliKelasNama }} )</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
