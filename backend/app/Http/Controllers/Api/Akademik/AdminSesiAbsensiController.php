<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Akademik\Traits\SesiAbsensiHelpers;
use App\Models\AbsensiPengajar;
use App\Models\AbsensiSantri;
use App\Models\DataKelas;
use App\Models\DataPetugas;
use App\Models\DataSantri;
use App\Models\DataTahunAjaran;
use App\Models\DataUnit;
use App\Models\JadwalPembelajaran;
use App\Models\SesiAbsensi;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminSesiAbsensiController extends Controller
{
    use SesiAbsensiHelpers;

    public function bukaSesi(Request $request): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'id_jadwal' => ['required', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'tanggal' => ['nullable', 'string'],
            'id_petugas_hadir' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'status_kehadiran' => ['nullable', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT', 'ALFA'])],
            'menit_terlambat' => ['nullable', 'integer', 'min:0'],
            'catat_absensi_pengajar' => ['nullable', 'boolean'],
            'status_sesi' => ['nullable', 'string', Rule::in(['BERLANGSUNG', 'SELESAI', 'BATAL'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $jadwal = JadwalPembelajaran::with('kelasMapel.mataPelajaran', 'kelasMapel.kelas')->findOrFail((int) $validated['id_jadwal']);
        $tanggal = $this->resolveTanggalSesi($validated['tanggal'] ?? null);
        $hariJadwal = strtoupper(trim((string) ($jadwal->hari ?? '')));
        $hariTanggal = $this->resolveHariIndonesia($tanggal);

        // Jika jadwal tidak memiliki hari (fleksibel/belum di-set), kita izinkan tanpa validasi hari.
        if ($hariJadwal !== '' && $hariTanggal !== $hariJadwal) {
            return response()->json([
                'message' => "Tanggal sesi harus mengikuti hari jadwal {$hariJadwal}. Tanggal yang dipilih adalah {$hariTanggal}.",
            ], 422);
        }

        $existingSesi = SesiAbsensi::query()
            ->where('id_jadwal', (int) $validated['id_jadwal'])
            ->whereDate('tanggal', $tanggal)
            ->whereNotIn('status_sesi', ['BATAL']) // Sesi BATAL diabaikan agar admin bisa buka sesi baru
            ->first();

        if ($existingSesi) {
            return response()->json([
                'message' => 'Sesi untuk jadwal dan tanggal ini sudah ada.',
                'data' => $this->loadSesi((int) $existingSesi->id_sesi),
            ], 409);
        }

        $idPetugasHadir = isset($validated['id_petugas_hadir'])
            ? (int) $validated['id_petugas_hadir']
            : (int) ($jadwal->kelasMapel?->id_petugas ?? 0);

        if ($idPetugasHadir <= 0) {
            throw ValidationException::withMessages([
                'id_petugas_hadir' => ['Petugas hadir wajib diisi jika jadwal belum memiliki petugas.'],
            ]);
        }

        $waktuMulai = $jadwal->jam_mulai ?: now(config('app.timezone'))->format('H:i:s');
        $waktuSelesai = $jadwal->jam_selesai ?: now(config('app.timezone'))->format('H:i:s');
        $catatAbsensiPengajar = array_key_exists('catat_absensi_pengajar', $validated)
            ? (bool) $validated['catat_absensi_pengajar']
            : true;
        $statusKehadiran = strtoupper(trim((string) ($validated['status_kehadiran'] ?? 'HADIR')));

        try {
            $sesi = DB::transaction(function () use ($jadwal, $tanggal, $validated, $idPetugasHadir, $waktuMulai, $waktuSelesai, $catatAbsensiPengajar, $statusKehadiran, $admin) {
                $sesi = SesiAbsensi::create([
                    'id_jadwal' => (int) $jadwal->id_jadwal,
                    'id_petugas_hadir' => $idPetugasHadir,
                    'tanggal' => $tanggal,
                    'waktu_mulai' => $waktuMulai,
                    'waktu_selesai' => $waktuSelesai,
                    'status_sesi' => $validated['status_sesi'] ?? 'SELESAI',
                    'keterangan' => $validated['keterangan'] ?? null,
                ]);

                if ($catatAbsensiPengajar) {
                    $menitTerlambat = $validated['menit_terlambat']
                        ?? $this->hitungMenitTerlambat(
                            $tanggal,
                            $jadwal->jam_mulai,
                            (string) $waktuMulai,
                            $statusKehadiran
                        );

                    $ap = AbsensiPengajar::query()
                        ->where('id_sesi', (int) $sesi->id_sesi)
                        ->where('id_petugas', (int) $idPetugasHadir)
                        ->where('tanggal', $tanggal)
                        ->first();

                    $payload = [
                        'status_kehadiran' => $statusKehadiran,
                        'menit_terlambat' => (int) $menitTerlambat,
                        'keterangan' => $validated['keterangan'] ?? null,
                        'input_oleh' => (int) $admin->id_petugas,
                    ];

                    if ($ap) {
                        $ap->update($payload);
                    } else {
                        AbsensiPengajar::create(array_merge([
                            'id_sesi' => (int) $sesi->id_sesi,
                            'id_petugas' => (int) $idPetugasHadir,
                            'tanggal' => $tanggal,
                        ], $payload));
                    }
                }

                return $sesi;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                $existing = SesiAbsensi::query()
                    ->where('id_jadwal', (int) $validated['id_jadwal'])
                    ->whereDate('tanggal', $tanggal)
                    ->first();

                return response()->json([
                    'message' => 'Sesi sudah ada (duplicate detected).',
                    'data' => $existing ? $this->loadSesi((int) $existing->id_sesi) : null,
                ], 409);
            }

            throw $exception;
        }

        // Log Aktivitas
        $namaMapelBuka = $jadwal->kelasMapel?->mataPelajaran?->nama_mapel ?? 'Mapel ?';
        $namaKelasBuka = $jadwal->kelasMapel?->kelas?->nama_kelas ?? 'Kelas ?';
        $tglBuka = \Carbon\Carbon::parse($tanggal)->translatedFormat('d M Y');
        \App\Models\LogAktivitas::create([
            'id_petugas' => (int) $admin->id_petugas,
            'jenis_aksi' => 'CREATE',
            'modul' => 'ABSENSI',
            'deskripsi' => 'Admin membuka sesi absensi: ' . $namaMapelBuka . ' - ' . $namaKelasBuka . ' (' . $tglBuka . ')',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Sesi absensi berhasil dibuka oleh admin.',
            'data' => $this->loadSesi((int) $sesi->id_sesi),
        ], 201);
    }

    public function upsertAbsensiPengajar(Request $request, int $id): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'id_petugas' => ['required', 'integer', 'exists:data_petugas,id_petugas'],
            'status_kehadiran' => ['required', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT', 'ALFA'])],
            'menit_terlambat' => ['nullable', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $sesi = SesiAbsensi::with('jadwal.kelasMapel.mataPelajaran', 'jadwal.kelasMapel.kelas')->findOrFail($id);

        $statusKehadiran = strtoupper(trim((string) $validated['status_kehadiran']));
        $menitTerlambat = $validated['menit_terlambat']
            ?? $this->hitungMenitTerlambat(
                (string) $sesi->tanggal,
                $sesi->jadwal?->jam_mulai,
                (string) ($sesi->waktu_mulai ?? $sesi->jadwal?->jam_mulai),
                $statusKehadiran
            );

        $existing = AbsensiPengajar::query()
            ->where('id_sesi', $sesi->id_sesi)
            ->where('id_petugas', (int) $validated['id_petugas'])
            ->first();

        $data = AbsensiPengajar::query()->updateOrCreate(
            [
                'id_sesi' => $sesi->id_sesi,
                'id_petugas' => (int) $validated['id_petugas'],
                'tanggal' => $sesi->tanggal,
            ],
            [
                'status_kehadiran' => $statusKehadiran,
                'menit_terlambat' => (int) $menitTerlambat,
                'keterangan' => $validated['keterangan'] ?? null,
                'input_oleh' => (int) $admin->id_petugas,
            ]
        );

        if ($existing) {
            if ($existing->status_kehadiran !== $statusKehadiran) {
                \App\Models\LogPerubahanAbsensi::create([
                    'tabel_terkait' => 'absensi_pengajar',
                    'id_record' => $data->id_abs_pengajar ?? $data->id,
                    'field_diubah' => 'status_kehadiran',
                    'nilai_lama' => $existing->status_kehadiran,
                    'nilai_baru' => $statusKehadiran,
                    'alasan_perubahan' => $validated['keterangan'] ?? 'Diubah oleh Admin',
                    'diubah_oleh' => (int) $admin->id_petugas,
                    'diubah_pada' => now(),
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        $namaGuruLog = $data->fresh('petugas')?->petugas?->nama_lengkap ?? 'Guru ID: ' . $validated['id_petugas'];
        $namaMapelLog = $sesi->jadwal?->kelasMapel?->mataPelajaran?->nama_mapel ?? 'Mapel ?';
        $namaKelasLog = $sesi->jadwal?->kelasMapel?->kelas?->nama_kelas ?? 'Kelas ?';
        $tglLog = \Carbon\Carbon::parse($sesi->tanggal)->translatedFormat('d M Y');
        \App\Models\LogAktivitas::create([
            'id_petugas' => (int) $admin->id_petugas,
            'jenis_aksi' => 'UPDATE',
            'modul' => 'ABSENSI',
            'deskripsi' => 'Admin memperbarui kehadiran ' . $namaGuruLog . ' pada ' . $namaMapelLog . ' - ' . $namaKelasLog . ' (' . $tglLog . ') → ' . $statusKehadiran,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Absensi petugas berhasil disimpan oleh admin.',
            'data' => $data->fresh('petugas'),
        ]);
    }

    public function deleteAbsensiPengajar(Request $request, int $id): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'id_petugas' => ['required', 'integer', 'exists:data_petugas,id_petugas'],
        ]);

        $deleted = AbsensiPengajar::query()
            ->where('id_sesi', $id)
            ->where('id_petugas', (int) $validated['id_petugas'])
            ->delete();

        if ($deleted < 1) {
            return response()->json([
                'message' => 'Data absensi petugas tidak ditemukan pada sesi ini.',
            ], 404);
        }

        return response()->json([
            'message' => 'Absensi petugas berhasil dihapus oleh admin.',
        ]);
    }

    public function upsertAbsensiSantri(Request $request, int $id): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'absensi' => ['required', 'array', 'min:1'],
            'absensi.*.nomor_induk' => ['required', 'string', 'exists:data_santri,nomor_induk'],
            'absensi.*.status_kehadiran' => ['required', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT', 'ALFA'])],
            'absensi.*.keterangan' => ['nullable', 'string'],
        ]);

        $sesi = $this->loadSesi($id);
        $kodeKelas = $sesi->jadwal?->kelasMapel?->kode_kelas;

        if (empty($kodeKelas)) {
            return response()->json([
                'message' => 'Kode kelas pada jadwal sesi tidak ditemukan.',
            ], 422);
        }

        $nomorIndukValid = DataSantri::query()
            ->where('kode_kelas', $kodeKelas)
            ->pluck('nomor_induk')
            ->flip();

        $inserted = 0;
        $updated = 0;
        $rejected = [];

        DB::transaction(function () use ($validated, $nomorIndukValid, $admin, $sesi, &$inserted, &$updated, &$rejected) {
            foreach ($validated['absensi'] as $row) {
                $nomorInduk = trim((string) $row['nomor_induk']);

                if (!$nomorIndukValid->has($nomorInduk)) {
                    $rejected[] = [
                        'nomor_induk' => $nomorInduk,
                        'message' => 'Santri tidak terdaftar di kelas sesi ini.',
                    ];
                    continue;
                }

                $payload = [
                    'status_kehadiran' => strtoupper(trim((string) $row['status_kehadiran'])),
                    'keterangan' => $row['keterangan'] ?? null,
                    'timestamp_input' => now(),
                    'input_oleh' => (int) $admin->id_petugas,
                ];

                $existing = AbsensiSantri::query()
                    ->where('id_sesi', $sesi->id_sesi)
                    ->where('nomor_induk', $nomorInduk)
                    ->first();

                if ($existing) {
                    $oldStatus = $existing->status_kehadiran;
                    $newStatus = strtoupper(trim((string) $row['status_kehadiran']));

                    if ($oldStatus !== $newStatus) {
                        \App\Models\LogPerubahanAbsensi::create([
                            'tabel_terkait' => 'absensi_santri',
                            'id_record' => $existing->id_absensi ?? $existing->id,
                            'field_diubah' => 'status_kehadiran',
                            'nilai_lama' => $oldStatus,
                            'nilai_baru' => $newStatus,
                            'alasan_perubahan' => $row['keterangan'] ?? 'Diubah oleh Admin',
                            'diubah_oleh' => (int) $admin->id_petugas,
                            'diubah_pada' => now(),
                            'ip_address' => request()->ip(),
                        ]);
                    }

                    $existing->update($payload);
                    $updated++;
                    continue;
                }

                AbsensiSantri::create([
                    'id_sesi' => $sesi->id_sesi,
                    'nomor_induk' => $nomorInduk,
                    ...$payload,
                ]);
                $inserted++;
            }
        });

        // Log Aktivitas
        $namaMapelSantri = $sesi->jadwal?->kelasMapel?->mataPelajaran?->nama_mapel ?? 'Mapel ?';
        $namaKelasSantri = $sesi->jadwal?->kelasMapel?->kelas?->nama_kelas ?? 'Kelas ?';
        $tglSantri = \Carbon\Carbon::parse($sesi->tanggal)->translatedFormat('d M Y');
        \App\Models\LogAktivitas::create([
            'id_petugas' => (int) $admin->id_petugas,
            'jenis_aksi' => 'UPDATE',
            'modul' => 'ABSENSI',
            'deskripsi' => 'Admin menyimpan absensi santri: ' . $namaMapelSantri . ' - ' . $namaKelasSantri . ' (' . $tglSantri . ') — Tambah: ' . $inserted . ', Edit: ' . $updated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Absensi santri berhasil disimpan oleh admin.',
            'data' => [
                'id_sesi' => $sesi->id_sesi,
                'inserted' => $inserted,
                'updated' => $updated,
                'rejected' => $rejected,
            ],
        ]);
    }

    public function deleteAbsensiSantri(Request $request, int $id): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'nomor_induk' => ['required', 'string', 'exists:data_santri,nomor_induk'],
        ]);

        $deleted = AbsensiSantri::query()
            ->where('id_sesi', $id)
            ->where('nomor_induk', trim((string) $validated['nomor_induk']))
            ->delete();

        if ($deleted < 1) {
            return response()->json([
                'message' => 'Data absensi santri tidak ditemukan pada sesi ini.',
            ], 404);
        }

        return response()->json([
            'message' => 'Absensi santri berhasil dihapus oleh admin.',
        ]);
    }

    public function belumDiabsen(Request $request): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $tanggal = $this->resolveTanggalSesi($request->tanggal ?? null);
        $hari = $this->resolveHariIndonesia($tanggal);

        // Get all schedules for the specified day that are active
        $jadwalQuery = JadwalPembelajaran::with([
            'kelasMapel.kelas',
            'kelasMapel.mataPelajaran',
            'kelasMapel.petugas'
        ])
        ->where('hari', $hari)
        ->where('status', 'AKTIF');

        if ($request->filled('kode_unit')) {
            $jadwalQuery->whereHas('kelasMapel.kelas', function($q) use ($request) {
                $q->where('kode_unit', strtoupper($request->kode_unit));
            });
        }

        if ($request->filled('kode_kelas')) {
            $jadwalQuery->whereHas('kelasMapel', function($q) use ($request) {
                $q->where('kode_kelas', strtoupper($request->kode_kelas));
            });
        }

        if ($request->filled('tahun_ajaran') && $request->tahun_ajaran !== 'ALL') {
            $jadwalQuery->whereHas('kelasMapel', function($q) use ($request) {
                $q->where('tahun_ajaran', $request->tahun_ajaran);
            });
        }

        $semuaJadwal = $jadwalQuery->get();

        // Get all sessions already created for this date
        $sesiHariIni = SesiAbsensi::whereDate('tanggal', $tanggal)->pluck('id_jadwal')->toArray();

        // Filter schedules that don't have a session yet
        $belumDiabsen = $semuaJadwal->filter(function($jadwal) use ($sesiHariIni) {
            return !in_array($jadwal->id_jadwal, $sesiHariIni);
        })->values();

        // Format the response similarly to SesiAbsensiApiItem for easy frontend use
        $formatted = $belumDiabsen->map(function($jadwal) use ($tanggal) {
            return [
                'id_jadwal' => $jadwal->id_jadwal,
                'tanggal' => $tanggal,
                'hari' => $jadwal->hari,
                'jam_mulai' => $jadwal->jam_mulai,
                'jam_selesai' => $jadwal->jam_selesai,
                'mapel' => $jadwal->kelasMapel?->mataPelajaran?->nama_mapel ?? null,
                'kelas' => $jadwal->kelasMapel?->kelas?->nama_kelas ?? null,
                'id_petugas_hadir' => $jadwal->kelasMapel?->id_petugas ?? null,
                'petugas_hadir' => [
                    'nama_lengkap' => $jadwal->kelasMapel?->petugas?->nama_lengkap ?? null,
                ],
                'jadwal' => $jadwal
            ];
        });

        return response()->json([
            'data' => $formatted
        ]);
    }

    /**
     * Endpoint konsolidasi untuk halaman Guru Panel.
     * Menggantikan 4 request terpisah (jadwal, petugas, tahun_ajaran, sesi_hari_ini)
     * dengan 1 request yang menjalankan semua query secara efisien.
     *
     * GET /api/akademik/guru-panel/init
     */
    public function guruPanelInit(Request $request): JsonResponse
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        // Query 1: Jadwal aktif — relasi dengan select spesifik (hati-hati: hanya kolom yang ada di tabel)
        $jadwalQuery = JadwalPembelajaran::with([
            'kelasMapel:id_kelas_mapel,kode_kelas,kode_mapel,id_petugas,tahun_ajaran',
            'kelasMapel.kelas',          // load semua, tabel kecil (tidak ada kolom jenjang)
            'kelasMapel.mataPelajaran:kode_mapel,nama_mapel',
            'kelasMapel.petugas:id_petugas,nama_lengkap',
        ])
        ->where('status', 'AKTIF');

        // Filter tahun ajaran jika dikirim dari frontend (header global selector)
        if ($request->filled('tahun_ajaran')) {
            $tahunAjaranFilter = $request->input('tahun_ajaran');
            $jadwalQuery->whereHas('kelasMapel', function ($q) use ($tahunAjaranFilter) {
                $q->where('tahun_ajaran', $tahunAjaranFilter);
            });
        }

        $jadwal = $jadwalQuery->get(['id_jadwal', 'id_kelas_mapel', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan', 'status', 'tahun_ajaran']);

        // Query 2: Petugas aktif — hanya field minimal
        $petugas = DataPetugas::where('status', 'AKTIF')
            ->orderBy('nama_lengkap')
            ->get(['id_petugas', 'nama_lengkap', 'peran_akun']);

        // Query 3: Tahun ajaran — hanya 10 terbaru
        $tahunAjaran = DataTahunAjaran::orderByDesc('kode_tahun')
            ->limit(10)
            ->get();

        // Query 4: Sesi hari ini saja (bukan 100 historis!)
        $sesiHariIni = SesiAbsensi::with([
            'jadwal.kelasMapel:id_kelas_mapel,kode_kelas,kode_mapel,id_petugas,tahun_ajaran',
            'jadwal.kelasMapel.kelas',
            'jadwal.kelasMapel.mataPelajaran:kode_mapel,nama_mapel',
            'petugasHadir:id_petugas,nama_lengkap',
            'petugasPengganti:id_petugas,nama_lengkap',
        ])
        ->withCount('absensiSantri')
        ->whereDate('tanggal', $today)
        ->orderByDesc('id_sesi')
        ->get();

        return response()->json([
            'jadwal'        => $jadwal,
            'petugas'       => $petugas,
            'tahun_ajaran'  => $tahunAjaran,
            'sesi_hari_ini' => $sesiHariIni,
        ]);
    }

    public function presensiGuruInit(Request $request): JsonResponse
    {
        // 1. Petugas aktif
        $petugas = DataPetugas::where('status', 'AKTIF')
            ->orderBy('nama_lengkap')
            ->get(['id_petugas', 'nama_lengkap', 'peran_akun']);

        // 2. Jadwal aktif
        $jadwal = JadwalPembelajaran::with([
            'kelasMapel:id_kelas_mapel,kode_kelas,kode_mapel,id_petugas,tahun_ajaran',
            'kelasMapel.kelas', // no column selection for smaller tables to prevent issues
            'kelasMapel.mataPelajaran:kode_mapel,nama_mapel',
            'kelasMapel.petugas:id_petugas,nama_lengkap',
        ])
        ->where('status', 'AKTIF')
        ->get(['id_jadwal', 'id_kelas_mapel', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan', 'status', 'tahun_ajaran']);

        // 3. Unit aktif
        $unit = DataUnit::where('status', 'AKTIF')
            ->orderBy('nama_unit')
            ->get(['kode_unit', 'nama_unit']);

        // 4. Kelas aktif
        $kelas = DataKelas::where('status', 'AKTIF')
            ->orderBy('nama_kelas')
            ->get(['kode_kelas', 'nama_kelas', 'kode_unit', 'tahun_ajaran']);

        // 5. Tahun Ajaran
        $tahunAjaran = DataTahunAjaran::orderByDesc('kode_tahun')
            ->limit(10)
            ->get();

        return response()->json([
            'petugas'      => $petugas,
            'jadwal'       => $jadwal,
            'unit'         => $unit,
            'kelas'        => $kelas,
            'tahun_ajaran' => $tahunAjaran,
        ]);
    }

    public function presensiSantriInit(Request $request): JsonResponse
    {
        // 1. Unit aktif
        $unit = DataUnit::where('status', 'AKTIF')
            ->orderBy('nama_unit')
            ->get(['kode_unit', 'nama_unit']);

        // 2. Kelas aktif
        $kelas = DataKelas::where('status', 'AKTIF')
            ->orderBy('nama_kelas')
            ->get(['kode_kelas', 'nama_kelas', 'kode_unit', 'tahun_ajaran']);

        // 3. Tahun Ajaran
        $tahunAjaran = DataTahunAjaran::orderByDesc('kode_tahun')
            ->limit(10)
            ->get();

        return response()->json([
            'unit'         => $unit,
            'kelas'        => $kelas,
            'tahun_ajaran' => $tahunAjaran,
        ]);
    }

    public function getLogAktivitas(Request $request): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $logQuery = \App\Models\LogAktivitas::query()
            ->where('modul', 'ABSENSI')
            ->leftJoin('data_petugas', 'log_aktivitas.id_petugas', '=', 'data_petugas.id_petugas')
            ->select('log_aktivitas.*', 'data_petugas.nama_lengkap as nama_admin')
            ->orderByDesc('log_aktivitas.created_at')
            ->orderByDesc('log_aktivitas.id_log_aktivitas');

        $limit = ($request->filled('tahun_ajaran') && $request->tahun_ajaran !== 'ALL') ? 500 : 100;
        $logs = $logQuery->limit($limit)->get();

        // Post-process deskripsi: ganti "guru ID: X", "sesi ID: Y", "jadwal ID: Z" dengan nama nyata (untuk log lama)
        $legacyPetugasIds = [];
        $legacySesiIds    = [];
        $legacyJadwalIds  = [];

        foreach ($logs as $log) {
            if (preg_match_all('/guru ID:\s*(\d+)/i', $log->deskripsi ?? '', $m)) {
                $legacyPetugasIds = array_merge($legacyPetugasIds, $m[1]);
            }
            if (preg_match_all('/sesi ID:\s*(\d+)/i', $log->deskripsi ?? '', $m)) {
                $legacySesiIds = array_merge($legacySesiIds, $m[1]);
            }
            if (preg_match_all('/jadwal ID:\s*(\d+)/i', $log->deskripsi ?? '', $m)) {
                $legacyJadwalIds = array_merge($legacyJadwalIds, $m[1]);
            }
        }

        $legacyPetugasMap = collect();
        if (!empty($legacyPetugasIds)) {
            $legacyPetugasMap = \App\Models\DataPetugas::whereIn('id_petugas', array_unique($legacyPetugasIds))
                ->get()->keyBy('id_petugas');
        }

        $legacySesiMap = collect();
        if (!empty($legacySesiIds)) {
            $legacySesiMap = SesiAbsensi::with([
                'jadwal.kelasMapel.mataPelajaran',
                'jadwal.kelasMapel.kelas',
            ])->whereIn('id_sesi', array_unique($legacySesiIds))->get()->keyBy('id_sesi');
        }

        $legacyJadwalMap = collect();
        if (!empty($legacyJadwalIds)) {
            $legacyJadwalMap = JadwalPembelajaran::with([
                'kelasMapel.mataPelajaran',
                'kelasMapel.kelas',
            ])->whereIn('id_jadwal', array_unique($legacyJadwalIds))->get()->keyBy('id_jadwal');
        }

        $logsFormatted = $logs->map(function ($log) use ($legacyPetugasMap, $legacySesiMap, $legacyJadwalMap) {
            $row = $log->toArray();
            $desc = $row['deskripsi'] ?? '';

            // Ganti "guru ID: X" → nama_lengkap
            $desc = preg_replace_callback('/guru ID:\s*(\d+)/i', function ($m) use ($legacyPetugasMap) {
                $p = $legacyPetugasMap->get((int) $m[1]);
                return $p ? $p->nama_lengkap : 'Guru #' . $m[1];
            }, $desc);

            // Ganti "sesi ID: X" → Mapel - Kelas (dd-mm-YYYY)
            $desc = preg_replace_callback('/sesi ID:\s*(\d+)/i', function ($m) use ($legacySesiMap) {
                $sesi = $legacySesiMap->get((int) $m[1]);
                if ($sesi) {
                    $mapel = $sesi->jadwal?->kelasMapel?->mataPelajaran?->nama_mapel ?? '-';
                    $kelas = $sesi->jadwal?->kelasMapel?->kelas?->nama_kelas ?? '-';
                    $tgl   = $sesi->tanggal ? \Carbon\Carbon::parse($sesi->tanggal)->format('d-m-Y') : '-';
                    return "{$mapel} - {$kelas} ({$tgl})";
                }
                return 'Sesi #' . $m[1];
            }, $desc);

            // Ganti "jadwal ID: X" → Mapel - Kelas
            $desc = preg_replace_callback('/jadwal ID:\s*(\d+)/i', function ($m) use ($legacyJadwalMap) {
                $jadwal = $legacyJadwalMap->get((int) $m[1]);
                if ($jadwal) {
                    $mapel = $jadwal->kelasMapel?->mataPelajaran?->nama_mapel ?? '-';
                    $kelas = $jadwal->kelasMapel?->kelas?->nama_kelas ?? '-';
                    return "{$mapel} - {$kelas}";
                }
                return 'Jadwal #' . $m[1];
            }, $desc);

            $row['deskripsi'] = $desc;

            // Fix Timezone: DB stores UTC, Laravel incorrectly assumes local. We parse raw UTC and format to ISO-8601
            // We use substr to remove fractional seconds (e.g. .546323) which can cause JS Date.parse to misinterpret the timezone offset.
            if (!empty($log->getRawOriginal('created_at'))) {
                try {
                    $rawDate = substr($log->getRawOriginal('created_at'), 0, 19);
                    $row['created_at'] = \Carbon\Carbon::parse($rawDate, 'UTC')
                        ->timezone(config('app.timezone', 'Asia/Jakarta'))
                        ->toIso8601String();
                } catch (\Throwable $e) {}
            }

            return $row;
        });

        if ($request->filled('tahun_ajaran') && $request->tahun_ajaran !== 'ALL') {
            $ta = $request->tahun_ajaran;
            
            $validCombinations = \App\Models\DataKelasMapel::with(['mataPelajaran', 'kelas'])
                ->where('tahun_ajaran', $ta)
                ->get()
                ->map(function($km) {
                    $mapel = $km->mataPelajaran?->nama_mapel ?? '';
                    $kelas = $km->kelas?->nama_kelas ?? '';
                    return "{$mapel} - {$kelas}";
                })->filter()->unique()->toArray();

            $logsFormatted = $logsFormatted->filter(function($log) use ($validCombinations) {
                $desc = $log['deskripsi'] ?? '';
                foreach ($validCombinations as $combo) {
                    if ($combo !== ' - ' && str_contains($desc, $combo)) {
                        return true;
                    }
                }
                return false;
            })->values()->take(100);
        }

        $auditQuery = \App\Models\LogPerubahanAbsensi::query()
            ->leftJoin('data_petugas', 'log_perubahan_absensi.diubah_oleh', '=', 'data_petugas.id_petugas')
            ->select('log_perubahan_absensi.*', 'data_petugas.nama_lengkap as nama_admin')
            ->orderByDesc('log_perubahan_absensi.diubah_pada')
            ->orderByDesc('log_perubahan_absensi.id_log');

        if ($request->filled('tahun_ajaran') && $request->tahun_ajaran !== 'ALL') {
            $ta = $request->tahun_ajaran;
            $auditQuery->where(function($q) use ($ta) {
                $q->where(function($q1) use ($ta) {
                    $q1->where('tabel_terkait', 'absensi_santri')
                       ->whereExists(function($q2) use ($ta) {
                           $q2->select(\Illuminate\Support\Facades\DB::raw(1))
                              ->from('absensi_santri')
                              ->join('sesi_absensi', 'absensi_santri.id_sesi', '=', 'sesi_absensi.id_sesi')
                              ->join('jadwal_pembelajaran', 'sesi_absensi.id_jadwal', '=', 'jadwal_pembelajaran.id_jadwal')
                              ->join('data_kelas_mapel', 'jadwal_pembelajaran.id_kelas_mapel', '=', 'data_kelas_mapel.id_kelas_mapel')
                              ->whereColumn('log_perubahan_absensi.id_record', 'absensi_santri.id_absensi')
                              ->where('data_kelas_mapel.tahun_ajaran', $ta);
                       });
                })->orWhere(function($q1) use ($ta) {
                    $q1->where('tabel_terkait', 'absensi_pengajar')
                       ->whereExists(function($q2) use ($ta) {
                           $q2->select(\Illuminate\Support\Facades\DB::raw(1))
                              ->from('absensi_pengajar')
                              ->join('sesi_absensi', 'absensi_pengajar.id_sesi', '=', 'sesi_absensi.id_sesi')
                              ->join('jadwal_pembelajaran', 'sesi_absensi.id_jadwal', '=', 'jadwal_pembelajaran.id_jadwal')
                              ->join('data_kelas_mapel', 'jadwal_pembelajaran.id_kelas_mapel', '=', 'data_kelas_mapel.id_kelas_mapel')
                              ->whereColumn('log_perubahan_absensi.id_record', 'absensi_pengajar.id_abs_pengajar')
                              ->where('data_kelas_mapel.tahun_ajaran', $ta);
                       });
                });
            });
        }

        $auditLogs = $auditQuery->limit(100)->get();

        // Hydrate info_jadwal dan nama_subjek (Santri/Ustadz) yang bersangkutan
        $santriRecordIds = [];
        $pengajarRecordIds = [];

        foreach ($auditLogs as $audit) {
            if ($audit->tabel_terkait === 'absensi_santri') {
                $santriRecordIds[] = (int) $audit->id_record;
            } elseif ($audit->tabel_terkait === 'absensi_pengajar') {
                $pengajarRecordIds[] = (int) $audit->id_record;
            }
        }

        $absensiSantriMap = collect();
        if (!empty($santriRecordIds)) {
            $absensiSantriMap = \App\Models\AbsensiSantri::with([
                'santri',
                'sesi.jadwal.kelasMapel.mataPelajaran',
                'sesi.jadwal.kelasMapel.kelas'
            ])
            ->whereIn('id_absensi', $santriRecordIds)
            ->get()
            ->keyBy('id_absensi');
        }

        $absensiPengajarMap = collect();
        if (!empty($pengajarRecordIds)) {
            $absensiPengajarMap = \App\Models\AbsensiPengajar::with([
                'petugas',
                'sesi.jadwal.kelasMapel.mataPelajaran',
                'sesi.jadwal.kelasMapel.kelas'
            ])
            ->whereIn('id_abs_pengajar', $pengajarRecordIds)
            ->get()
            ->keyBy('id_abs_pengajar');
        }

        // Map to plain arrays so extra fields (info_jadwal, nama_subjek) are included in JSON serialization
        $auditFormatted = $auditLogs->map(function ($audit) use ($absensiSantriMap, $absensiPengajarMap) {
            $row = $audit->toArray();
            $infoJadwal = null;
            $namaSubjek = null;

            if ($audit->tabel_terkait === 'absensi_santri') {
                $absSantri = $absensiSantriMap->get((int) $audit->id_record);
                if ($absSantri) {
                    $namaSubjek = $absSantri->santri?->nama_lengkap_santri ?? $absSantri->nomor_induk;
                    $mapel = $absSantri->sesi?->jadwal?->kelasMapel?->mataPelajaran?->nama_mapel ?? '-';
                    $kelas = $absSantri->sesi?->jadwal?->kelasMapel?->kelas?->nama_kelas ?? '-';
                    $tanggal = $absSantri->sesi?->tanggal ? \Carbon\Carbon::parse($absSantri->sesi->tanggal)->format('d-m-Y') : '-';
                    $infoJadwal = "{$mapel} ({$kelas}) [{$tanggal}]";
                }
            } elseif ($audit->tabel_terkait === 'absensi_pengajar') {
                $absPengajar = $absensiPengajarMap->get((int) $audit->id_record);
                if ($absPengajar) {
                    $namaSubjek = $absPengajar->petugas?->nama_lengkap ?? 'Ustadz ID: ' . $absPengajar->id_petugas;
                    $mapel = $absPengajar->sesi?->jadwal?->kelasMapel?->mataPelajaran?->nama_mapel ?? '-';
                    $kelas = $absPengajar->sesi?->jadwal?->kelasMapel?->kelas?->nama_kelas ?? '-';
                    $tanggal = $absPengajar->sesi?->tanggal ? \Carbon\Carbon::parse($absPengajar->sesi->tanggal)->format('d-m-Y') : '-';
                    $infoJadwal = "{$mapel} ({$kelas}) [{$tanggal}]";
                }
            }

            $row['info_jadwal'] = $infoJadwal ?? 'Sesi / Jadwal #' . $audit->id_record;
            $row['nama_subjek'] = $namaSubjek ?? '-';
            return $row;
        });

        return response()->json([
            'status' => 'success',
            'log_aktivitas' => $logsFormatted,
            'log_audit' => $auditFormatted,
        ]);
    }
}
