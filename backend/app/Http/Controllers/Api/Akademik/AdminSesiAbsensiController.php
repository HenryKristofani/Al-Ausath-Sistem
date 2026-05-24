<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Akademik\Traits\SesiAbsensiHelpers;
use App\Models\AbsensiPengajar;
use App\Models\AbsensiSantri;
use App\Models\DataSantri;
use App\Models\JadwalPembelajaran;
use App\Models\SesiAbsensi;
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

        $jadwal = JadwalPembelajaran::with('kelasMapel')->findOrFail((int) $validated['id_jadwal']);
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

                    AbsensiPengajar::query()->updateOrCreate(
                        [
                            'id_sesi' => (int) $sesi->id_sesi,
                            'id_petugas' => (int) $idPetugasHadir,
                            'tanggal' => $tanggal,
                        ],
                        [
                            'status_kehadiran' => $statusKehadiran,
                            'menit_terlambat' => (int) $menitTerlambat,
                            'keterangan' => $validated['keterangan'] ?? null,
                            'input_oleh' => (int) $admin->id_petugas,
                        ]
                    );
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

        $sesi = SesiAbsensi::with('jadwal')->findOrFail($id);

        $statusKehadiran = strtoupper(trim((string) $validated['status_kehadiran']));
        $menitTerlambat = $validated['menit_terlambat']
            ?? $this->hitungMenitTerlambat(
                (string) $sesi->tanggal,
                $sesi->jadwal?->jam_mulai,
                (string) ($sesi->waktu_mulai ?? $sesi->jadwal?->jam_mulai),
                $statusKehadiran
            );

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
}
