<?php

namespace App\Http\Controllers\Api\Akademik;

use App\Http\Controllers\Controller;
use App\Models\AbsensiPengajar;
use App\Models\AbsensiSantri;
use App\Models\DataPetugas;
use App\Models\DataSantri;
use App\Models\JadwalPembelajaran;
use App\Models\SesiAbsensi;
use App\Http\Controllers\Api\Akademik\Traits\SesiAbsensiHelpers;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SesiAbsensiController extends Controller
{
    use SesiAbsensiHelpers;

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);

        $query = SesiAbsensi::query()
            ->with([
                'jadwal.kelasMapel.kelas',
                'jadwal.kelasMapel.mataPelajaran',
                'jadwal.kelasMapel.petugas',
                'petugasHadir',
                'petugasPengganti',
            ])
            ->withCount('absensiSantri')
            ->when($request->filled('id_jadwal'), fn ($q) => $q->where('id_jadwal', (int) $request->id_jadwal))
            ->when($request->filled('tanggal'), fn ($q) => $q->whereDate('tanggal', $request->tanggal))
            ->when($request->filled('status_sesi'), fn ($q) => $q->where('status_sesi', strtoupper(trim((string) $request->status_sesi))))
            ->when($request->filled('id_petugas_hadir'), fn ($q) => $q->where('id_petugas_hadir', (int) $request->id_petugas_hadir))
            ->when($request->filled('q'), function ($q) use ($request) {
                $keyword = trim((string) $request->q);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('status_sesi', 'like', "%{$keyword}%")
                        ->orWhere('keterangan', 'like', "%{$keyword}%")
                        ->orWhereHas('jadwal.kelasMapel.kelas', function ($kelasQuery) use ($keyword) {
                            $kelasQuery
                                ->where('kode_kelas', 'like', "%{$keyword}%")
                                ->orWhere('nama_kelas', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('jadwal.kelasMapel.mataPelajaran', function ($mapelQuery) use ($keyword) {
                            $mapelQuery
                                ->where('kode_mapel', 'like', "%{$keyword}%")
                                ->orWhere('nama_mapel', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id_sesi');

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->loadSesi($id)]);
    }

    public function aktif(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_jadwal' => ['required', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'tanggal' => ['required', 'string'],
        ]);

        $tanggal = $this->resolveTanggalSesi($validated['tanggal']);

        $sesi = SesiAbsensi::with([
            'jadwal.kelasMapel.kelas',
            'jadwal.kelasMapel.mataPelajaran',
            'jadwal.kelasMapel.petugas',
            'petugasHadir',
            'petugasPengganti',
            'absensiPengajar.petugas',
            'absensiSantri.santri',
        ])
            ->where('id_jadwal', (int) $validated['id_jadwal'])
            ->whereDate('tanggal', $tanggal)
            ->orderByDesc('id_sesi')
            ->first();

        if (!$sesi) {
            return response()->json([
                'message' => 'Sesi absensi belum ditemukan untuk jadwal dan tanggal tersebut.',
            ], 404);
        }

        return response()->json(['data' => $sesi]);
    }

    public function mulai(Request $request): JsonResponse
    {
        $petugas = $this->resolveCurrentPetugas($request);

        $validated = $request->validate([
            'id_jadwal' => ['required', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'tanggal' => ['nullable', 'string'],
            'status_kehadiran' => ['required', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $statusKehadiran = strtoupper(trim((string) $validated['status_kehadiran']));
        $tanggal = $this->resolveTanggalSesi($validated['tanggal'] ?? null);
        $waktuMulaiRealtime = now(config('app.timezone'))->format('H:i:s');

        $jadwal = JadwalPembelajaran::with('kelasMapel')->findOrFail((int) $validated['id_jadwal']);

        $hariJadwal = strtoupper(trim((string) ($jadwal->hari ?? '')));
        $hariTanggal = $this->resolveHariIndonesia($tanggal);

        if ($hariJadwal !== '' && $hariTanggal !== $hariJadwal) {
            return response()->json([
                'message' => "Sesi hanya dapat dimulai pada hari {$hariJadwal}. Tanggal yang dipilih adalah {$hariTanggal}.",
            ], 422);
        }

        $wajibIkutiJamJadwal = $statusKehadiran === 'HADIR';
        if ($wajibIkutiJamJadwal && !$this->isWaktuDalamRentangJadwal($tanggal, $waktuMulaiRealtime, $jadwal->jam_mulai, $jadwal->jam_selesai)) {
            return response()->json([
                'message' => 'Sesi hanya dapat dimulai pada rentang jam jadwal pembelajaran.',
                'data' => [
                    'jam_mulai_jadwal' => $jadwal->jam_mulai,
                    'jam_selesai_jadwal' => $jadwal->jam_selesai,
                    'waktu_mulai_realtime' => $waktuMulaiRealtime,
                ],
            ], 422);
        }

        $existingSesi = SesiAbsensi::query()
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->whereDate('tanggal', $tanggal)
            ->whereNotIn('status_sesi', ['BATAL']) // Sesi BATAL diabaikan agar bisa dimulai ulang
            ->first();

        $idPetugasJadwal = (int) ($jadwal->kelasMapel?->id_petugas ?? 0);
        if ($idPetugasJadwal <= 0) {
            return response()->json([
                'message' => 'Pengajar pada jadwal belum diatur. Silakan lengkapi data kelas mapel terlebih dahulu.',
            ], 422);
        }

        $idPetugasPengganti = (int) ($existingSesi?->id_petugas_pengganti ?? 0);
        $petugasSekarang = (int) $petugas->id_petugas;
        $bolehMulaiSesi = $petugasSekarang === $idPetugasJadwal
            || ($existingSesi !== null
                && $existingSesi->status_sesi === 'MENUNGGU_PENGGANTI'
                && $idPetugasPengganti > 0
                && $petugasSekarang === $idPetugasPengganti);

        if (!$bolehMulaiSesi) {
            return response()->json([
                'message' => 'Hanya pengajar utama atau pengajar pengganti yang ditetapkan yang dapat memulai sesi absensi.',
            ], 403);
        }

        if ($existingSesi) {
            if ($existingSesi->status_sesi !== 'MENUNGGU_PENGGANTI' || $petugasSekarang !== $idPetugasPengganti) {
                return response()->json([
                    'message' => 'Sesi untuk jadwal dan tanggal ini sudah ada. Gunakan sesi yang sudah berjalan atau pilih tanggal lain.',
                    'data' => $this->loadSesi((int) $existingSesi->id_sesi),
                ], 409);
            }

            if (!$this->isWaktuDalamRentangJadwal($tanggal, $waktuMulaiRealtime, $jadwal->jam_mulai, $jadwal->jam_selesai)) {
                return response()->json([
                    'message' => 'Sesi hanya dapat dimulai pada rentang jam jadwal pembelajaran.',
                    'data' => [
                        'jam_mulai_jadwal' => $jadwal->jam_mulai,
                        'jam_selesai_jadwal' => $jadwal->jam_selesai,
                        'waktu_mulai_realtime' => $waktuMulaiRealtime,
                    ],
                ], 422);
            }

            $sesi = DB::transaction(function () use ($existingSesi, $petugas, $validated, $statusKehadiran, $waktuMulaiRealtime, $jadwal, $tanggal) {
                $existingSesi->id_petugas_hadir = (int) $petugas->id_petugas;
                $existingSesi->waktu_mulai = $waktuMulaiRealtime;
                $existingSesi->status_sesi = 'BERLANGSUNG';
                $existingSesi->keterangan = $validated['keterangan'] ?? $existingSesi->keterangan;
                $existingSesi->save();

                $menitTerlambat = $this->hitungMenitTerlambat(
                    $tanggal,
                    $jadwal->jam_mulai,
                    $existingSesi->waktu_mulai,
                    'HADIR'
                );

                AbsensiPengajar::query()->updateOrCreate(
                    [
                        'id_sesi' => $existingSesi->id_sesi,
                        'id_petugas' => (int) $petugas->id_petugas,
                        'tanggal' => $tanggal,
                    ],
                    [
                        'status_kehadiran' => 'HADIR',
                        'menit_terlambat' => $menitTerlambat,
                        'keterangan' => $validated['keterangan'] ?? null,
                        'input_oleh' => (int) $petugas->id_petugas,
                    ]
                );

                return $existingSesi;
            });

            return response()->json([
                'message' => 'Sesi absensi berhasil dimulai oleh pengajar pengganti.',
                'data' => $this->loadSesi((int) $sesi->id_sesi),
            ], 200);
        }

        try {
            $sesi = DB::transaction(function () use ($jadwal, $tanggal, $petugas, $validated, $statusKehadiran, $waktuMulaiRealtime) {
                $sesi = new SesiAbsensi();
                $sesi->id_jadwal = $jadwal->id_jadwal;
                $sesi->tanggal = $tanggal;
                $sesi->id_petugas_hadir = (int) $petugas->id_petugas;
                $sesi->waktu_mulai = $statusKehadiran === 'HADIR' ? $waktuMulaiRealtime : null;
                $sesi->status_sesi = $statusKehadiran === 'HADIR' ? 'BERLANGSUNG' : 'MENUNGGU_PENGGANTI';
                $sesi->keterangan = $validated['keterangan'] ?? $sesi->keterangan;
                $sesi->save();

                $menitTerlambat = $this->hitungMenitTerlambat(
                    $tanggal,
                    $jadwal->jam_mulai,
                    $sesi->waktu_mulai,
                    $statusKehadiran
                );

                AbsensiPengajar::query()->updateOrCreate(
                    [
                        'id_sesi' => $sesi->id_sesi,
                        'id_petugas' => (int) $petugas->id_petugas,
                        'tanggal' => $tanggal,
                    ],
                    [
                        'status_kehadiran' => $statusKehadiran,
                        'menit_terlambat' => $menitTerlambat,
                        'keterangan' => $validated['keterangan'] ?? null,
                        'input_oleh' => (int) $petugas->id_petugas,
                    ]
                );

                return $sesi;
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueViolation($exception)) {
                $existing = SesiAbsensi::query()
                    ->where('id_jadwal', $jadwal->id_jadwal)
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
            'message' => 'Sesi absensi berhasil dimulai dan absensi pengajar tercatat.',
            'data' => $this->loadSesi((int) $sesi->id_sesi),
        ], 201);
    }

    public function setPengganti(Request $request, int $id): JsonResponse
    {
        $petugas = $this->resolveCurrentPetugas($request);

        $validated = $request->validate([
            'id_petugas_pengganti' => ['required', 'integer', 'exists:data_petugas,id_petugas'],
            'status_kehadiran' => ['nullable', 'string', Rule::in(['IZIN', 'SAKIT'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $sesi = SesiAbsensi::findOrFail($id);

        if ((int) $sesi->id_petugas_hadir !== (int) $petugas->id_petugas) {
            return response()->json([
                'message' => 'Hanya pengajar utama pada sesi ini yang dapat mengatur pengajar pengganti.',
            ], 403);
        }

        if ((int) $validated['id_petugas_pengganti'] === (int) $petugas->id_petugas) {
            throw ValidationException::withMessages([
                'id_petugas_pengganti' => ['Pengajar pengganti tidak boleh sama dengan pengajar utama.'],
            ]);
        }

        DB::transaction(function () use ($sesi, $petugas, $validated) {
            $sesi->id_petugas_pengganti = (int) $validated['id_petugas_pengganti'];
            $sesi->status_sesi = 'MENUNGGU_PENGGANTI';
            $sesi->keterangan = $validated['keterangan'] ?? $sesi->keterangan;
            $sesi->save();

            if (!empty($validated['status_kehadiran'])) {
                AbsensiPengajar::query()->updateOrCreate(
                    [
                        'id_sesi' => $sesi->id_sesi,
                        'id_petugas' => (int) $petugas->id_petugas,
                        'tanggal' => $sesi->tanggal,
                    ],
                    [
                        'status_kehadiran' => strtoupper($validated['status_kehadiran']),
                        'keterangan' => $validated['keterangan'] ?? null,
                        'input_oleh' => (int) $petugas->id_petugas,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Pengajar pengganti berhasil diatur.',
            'data' => $this->loadSesi($sesi->id_sesi),
        ]);
    }

    public function daftarSantri(int $id): JsonResponse
    {
        $sesi = $this->loadSesi($id);
        $kodeKelas = $sesi->jadwal?->kelasMapel?->kode_kelas;

        if (empty($kodeKelas)) {
            return response()->json([
                'message' => 'Kode kelas pada jadwal sesi tidak ditemukan.',
            ], 422);
        }

        $absensiPerSantri = AbsensiSantri::query()
            ->where('id_sesi', $sesi->id_sesi)
            ->get()
            ->keyBy('nomor_induk');

        $santri = DataSantri::query()
            ->where('kode_kelas', $kodeKelas)
            ->orderBy('nama_lengkap_santri')
            ->get(['id_santri', 'nomor_induk', 'nama_lengkap_santri', 'kode_kelas'])
            ->map(function ($item) use ($absensiPerSantri) {
                $absensi = $absensiPerSantri->get($item->nomor_induk);

                return [
                    'id_santri' => $item->id_santri,
                    'nomor_induk' => $item->nomor_induk,
                    'nama_lengkap_santri' => $item->nama_lengkap_santri,
                    'kode_kelas' => $item->kode_kelas,
                    'status_kehadiran' => $absensi?->status_kehadiran,
                    'keterangan' => $absensi?->keterangan,
                    'timestamp_input' => $absensi?->timestamp_input,
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'sesi' => $sesi,
                'total_santri' => $santri->count(),
                'santri' => $santri,
            ],
        ]);
    }

    public function inputAbsensiSantri(Request $request, int $id): JsonResponse
    {
        $petugas = $this->resolveCurrentPetugas($request);

        $validated = $request->validate([
            'absensi' => ['required', 'array', 'min:1'],
            'absensi.*.nomor_induk' => ['required', 'string', 'exists:data_santri,nomor_induk'],
            'absensi.*.status_kehadiran' => ['required', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT', 'ALFA'])],
            'absensi.*.keterangan' => ['nullable', 'string'],
        ]);

        $sesi = $this->loadSesi($id);

        if (!in_array((int) $petugas->id_petugas, [(int) $sesi->id_petugas_hadir, (int) ($sesi->id_petugas_pengganti ?? 0)], true)) {
            return response()->json([
                'message' => 'Anda tidak berhak menginput absensi santri pada sesi ini.',
            ], 403);
        }

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

        DB::transaction(function () use ($validated, $nomorIndukValid, $petugas, $sesi, &$inserted, &$updated, &$rejected) {
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
                    'input_oleh' => (int) $petugas->id_petugas,
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
            'message' => 'Input absensi santri selesai diproses.',
            'data' => [
                'id_sesi' => $sesi->id_sesi,
                'inserted' => $inserted,
                'updated' => $updated,
                'rejected' => $rejected,
            ],
        ]);
    }

    public function selesai(Request $request, int $id): JsonResponse
    {
        $petugas = $this->resolveCurrentPetugas($request);

        $validated = $request->validate([
            'status_sesi' => ['nullable', 'string', Rule::in(['SELESAI', 'BATAL'])],
            'keterangan' => ['nullable', 'string'],
        ]);

        $sesi = SesiAbsensi::with('jadwal')->findOrFail($id);

        if (!in_array((int) $petugas->id_petugas, [(int) $sesi->id_petugas_hadir, (int) ($sesi->id_petugas_pengganti ?? 0)], true)) {
            return response()->json([
                'message' => 'Anda tidak berhak menutup sesi ini.',
            ], 403);
        }

        $sesi->waktu_selesai = $sesi->jadwal?->jam_selesai ?? now()->format('H:i:s');
        $sesi->status_sesi = strtoupper(trim((string) ($validated['status_sesi'] ?? 'SELESAI')));
        if (array_key_exists('keterangan', $validated)) {
            $sesi->keterangan = $validated['keterangan'];
        }
        $sesi->save();

        return response()->json([
            'message' => 'Sesi absensi berhasil ditutup.',
            'data' => $this->loadSesi($sesi->id_sesi),
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $petugas = $this->resolveCurrentPetugas($request);

        $validated = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $sesi = SesiAbsensi::with(['absensiSantri', 'absensiPengajar'])->findOrFail($id);

        // Cek izin: hanya pengajar pada sesi ini
        if ((int) $sesi->id_petugas_hadir !== (int) $petugas->id_petugas) {
            return response()->json([
                'message' => 'Hanya pengajar pada sesi ini yang dapat membatalkan sesi.',
            ], 403);
        }

        // Cek status: tidak boleh SELESAI atau BATAL
        if (in_array($sesi->status_sesi, ['SELESAI', 'BATAL'], true)) {
            return response()->json([
                'message' => 'Sesi absensi tidak dapat dibatalkan karena statusnya sudah ' . $sesi->status_sesi . '.',
                'data' => [
                    'id_sesi' => $sesi->id_sesi,
                    'status_sesi' => $sesi->status_sesi,
                ],
            ], 422);
        }

        // Cek data absensi santri: jika ada, tidak boleh cancel
        if ($sesi->absensiSantri->count() > 0) {
            return response()->json([
                'message' => 'Sesi absensi tidak dapat dibatalkan karena sudah ada data absensi santri.',
                'data' => [
                    'id_sesi' => $sesi->id_sesi,
                    'absensi_santri_count' => $sesi->absensiSantri->count(),
                ],
            ], 422);
        }

        // Simpan info untuk response sebelum dihapus
        $idSesi = $sesi->id_sesi;
        $idJadwal = $sesi->id_jadwal;
        $tanggal = $sesi->tanggal;

        // Cancel berhasil: hapus sesi dan semua draft absensi pengajar secara permanen
        // agar unique constraint (id_jadwal, tanggal) tidak menghalangi sesi baru di jadwal yang sama
        DB::transaction(function () use ($sesi) {
            // Hapus draft absensi pengajar terlebih dahulu (foreign key)
            AbsensiPengajar::where('id_sesi', $sesi->id_sesi)->delete();

            // Hapus sesi sepenuhnya sehingga jadwal bebas digunakan kembali
            $sesi->delete();
        });

        return response()->json([
            'message' => 'Sesi absensi berhasil dibatalkan. Jadwal dapat digunakan kembali.',
            'data' => [
                'id_sesi' => $idSesi,
                'id_jadwal' => $idJadwal,
                'tanggal' => $tanggal,
                'status_sesi' => 'DIHAPUS',
                'message_detail' => 'Sesi draft berhasil dihapus, jadwal siap dipakai ulang.',
            ],
        ], 200);
    }


}
