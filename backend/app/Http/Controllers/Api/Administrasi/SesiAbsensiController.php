<?php

namespace App\Http\Controllers\Api\Administrasi;

use App\Http\Controllers\Controller;
use App\Models\AbsensiPengajar;
use App\Models\AbsensiSantri;
use App\Models\DataPetugas;
use App\Models\DataSantri;
use App\Models\JadwalPembelajaran;
use App\Models\SesiAbsensi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SesiAbsensiController extends Controller
{
    /**
     * List sesi absensi.
     */
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

    /**
     * Detail sesi absensi.
     */
    public function show(int $id): JsonResponse
    {
        $sesi = SesiAbsensi::with([
            'jadwal.kelasMapel.kelas',
            'jadwal.kelasMapel.mataPelajaran',
            'jadwal.kelasMapel.petugas',
            'petugasHadir',
            'petugasPengganti',
            'absensiPengajar.petugas',
            'absensiSantri.santri',
        ])->findOrFail($id);

        return response()->json(['data' => $sesi]);
    }

    /**
     * Cari sesi aktif berdasarkan jadwal dan tanggal.
     */
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

    /**
     * Pengajar absen diri dan mulai sesi.
     */
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

        $idPetugasJadwal = (int) ($jadwal->kelasMapel?->id_petugas ?? 0);
        if ($idPetugasJadwal <= 0) {
            return response()->json([
                'message' => 'Pengajar pada jadwal belum diatur. Silakan lengkapi data kelas mapel terlebih dahulu.',
            ], 422);
        }

        if ((int) $petugas->id_petugas !== $idPetugasJadwal) {
            return response()->json([
                'message' => 'Hanya pengajar yang terdaftar pada jadwal ini yang dapat memulai sesi absensi.',
            ], 403);
        }

        $existingSesi = SesiAbsensi::query()
            ->where('id_jadwal', $jadwal->id_jadwal)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($existingSesi) {
            return response()->json([
                'message' => 'Sesi untuk jadwal dan tanggal ini sudah ada. Gunakan sesi yang sudah berjalan atau pilih tanggal lain.',
                'data' => $this->loadSesi((int) $existingSesi->id_sesi),
            ], 409);
        }

        $sesi = DB::transaction(function () use ($jadwal, $tanggal, $petugas, $validated, $statusKehadiran, $waktuMulaiRealtime) {
            $sesi = new SesiAbsensi();
            $sesi->id_jadwal = $jadwal->id_jadwal;
            $sesi->tanggal = $tanggal;

            $sesi->id_petugas_hadir = (int) $petugas->id_petugas;
            $sesi->waktu_mulai = $waktuMulaiRealtime;
            $sesi->status_sesi = 'BERLANGSUNG';
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

        return response()->json([
            'message' => 'Sesi absensi berhasil dimulai dan absensi pengajar tercatat.',
            'data' => $this->loadSesi($sesi->id_sesi),
        ], 201);
    }

    /**
     * Set atau ubah pengajar pengganti pada sesi yang berjalan.
     */
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

    /**
     * Daftar santri untuk absensi pada sesi tertentu.
     */
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

    /**
     * Input absensi santri untuk sesi tertentu.
     */
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

    /**
     * Tutup sesi absensi.
     */
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

    /**
     * Rekap absensi per santri.
     */
    public function rekapSantri(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kode_kelas' => ['nullable', 'string', 'max:20'],
            'nomor_induk' => ['nullable', 'string', 'max:20'],
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 's.id_sesi', '=', 'a.id_sesi')
            ->join('data_santri as ds', 'ds.nomor_induk', '=', 'a.nomor_induk')
            ->leftJoin('jadwal_pembelajaran as j', 'j.id_jadwal', '=', 's.id_jadwal')
            ->leftJoin('data_kelas_mapel as km', 'km.id_kelas_mapel', '=', 'j.id_kelas_mapel')
            ->leftJoin('data_kelas as k', 'k.kode_kelas', '=', 'ds.kode_kelas')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('s.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('s.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('ds.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['nomor_induk']), fn ($q) => $q->where('ds.nomor_induk', $validated['nomor_induk']))
            ->when(!empty($validated['id_jadwal']), fn ($q) => $q->where('s.id_jadwal', (int) $validated['id_jadwal']))
            ->when(!empty($validated['q']), function ($q) use ($validated) {
                $keyword = trim((string) $validated['q']);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('ds.nomor_induk', 'like', "%{$keyword}%")
                        ->orWhere('ds.nama_lengkap_santri', 'like', "%{$keyword}%")
                        ->orWhere('k.nama_kelas', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('ds.nomor_induk')
            ->selectRaw('ds.nama_lengkap_santri')
            ->selectRaw('ds.kode_kelas')
            ->selectRaw('k.nama_kelas')
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa")
            ->groupBy('ds.nomor_induk', 'ds.nama_lengkap_santri', 'ds.kode_kelas', 'k.nama_kelas')
            ->orderBy('ds.kode_kelas')
            ->orderBy('ds.nama_lengkap_santri');

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(function ($item) {
            $total = (int) $item->total_pertemuan;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            return $item;
        });

        return response()->json($rows);
    }

    /**
     * Rekap absensi per kelas.
     */
    public function rekapKelas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'kode_kelas' => ['nullable', 'string', 'max:20'],
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('absensi_santri as a')
            ->join('sesi_absensi as s', 's.id_sesi', '=', 'a.id_sesi')
            ->join('data_santri as ds', 'ds.nomor_induk', '=', 'a.nomor_induk')
            ->leftJoin('data_kelas as k', 'k.kode_kelas', '=', 'ds.kode_kelas')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('s.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('s.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('ds.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['id_jadwal']), fn ($q) => $q->where('s.id_jadwal', (int) $validated['id_jadwal']))
            ->when(!empty($validated['q']), function ($q) use ($validated) {
                $keyword = trim((string) $validated['q']);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('ds.kode_kelas', 'like', "%{$keyword}%")
                        ->orWhere('k.nama_kelas', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('ds.kode_kelas')
            ->selectRaw('k.nama_kelas')
            ->selectRaw('COUNT(*) as total_entri_absensi')
            ->selectRaw('COUNT(DISTINCT s.id_sesi) as total_sesi')
            ->selectRaw('COUNT(DISTINCT ds.nomor_induk) as total_santri_tercatat')
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw("SUM(CASE WHEN a.status_kehadiran = 'ALFA' THEN 1 ELSE 0 END) as jumlah_alfa")
            ->groupBy('ds.kode_kelas', 'k.nama_kelas')
            ->orderBy('ds.kode_kelas');

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(function ($item) {
            $total = (int) $item->total_entri_absensi;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            return $item;
        });

        return response()->json($rows);
    }

    /**
     * Rekap kehadiran petugas.
     */
    public function rekapPetugas(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'id_petugas' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'kode_kelas' => ['nullable', 'string', 'max:20'],
            'id_jadwal' => ['nullable', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('absensi_pengajar as ap')
            ->join('data_petugas as p', 'p.id_petugas', '=', 'ap.id_petugas')
            ->leftJoin('sesi_absensi as s', 's.id_sesi', '=', 'ap.id_sesi')
            ->leftJoin('jadwal_pembelajaran as j', 'j.id_jadwal', '=', 's.id_jadwal')
            ->leftJoin('data_kelas_mapel as km', 'km.id_kelas_mapel', '=', 'j.id_kelas_mapel')
            ->when(!empty($validated['tanggal_mulai']), fn ($q) => $q->whereDate('ap.tanggal', '>=', $validated['tanggal_mulai']))
            ->when(!empty($validated['tanggal_selesai']), fn ($q) => $q->whereDate('ap.tanggal', '<=', $validated['tanggal_selesai']))
            ->when(!empty($validated['id_petugas']), fn ($q) => $q->where('ap.id_petugas', (int) $validated['id_petugas']))
            ->when(!empty($validated['kode_kelas']), fn ($q) => $q->where('km.kode_kelas', $validated['kode_kelas']))
            ->when(!empty($validated['id_jadwal']), fn ($q) => $q->where('s.id_jadwal', (int) $validated['id_jadwal']))
            ->when(!empty($validated['q']), function ($q) use ($validated) {
                $keyword = trim((string) $validated['q']);
                $q->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where('p.nama_lengkap', 'like', "%{$keyword}%")
                        ->orWhere('p.peran_akun', 'like', "%{$keyword}%");
                });
            })
            ->selectRaw('ap.id_petugas')
            ->selectRaw('p.nama_lengkap')
            ->selectRaw('p.peran_akun')
            ->selectRaw('COUNT(*) as total_pertemuan')
            ->selectRaw("SUM(CASE WHEN ap.status_kehadiran = 'HADIR' THEN 1 ELSE 0 END) as jumlah_hadir")
            ->selectRaw("SUM(CASE WHEN ap.status_kehadiran = 'IZIN' THEN 1 ELSE 0 END) as jumlah_izin")
            ->selectRaw("SUM(CASE WHEN ap.status_kehadiran = 'SAKIT' THEN 1 ELSE 0 END) as jumlah_sakit")
            ->selectRaw('SUM(COALESCE(ap.menit_terlambat, 0)) as total_menit_terlambat')
            ->selectRaw("AVG(CASE WHEN ap.status_kehadiran = 'HADIR' THEN COALESCE(ap.menit_terlambat, 0) END) as rata_menit_terlambat_hadir")
            ->groupBy('ap.id_petugas', 'p.nama_lengkap', 'p.peran_akun')
            ->orderBy('p.nama_lengkap');

        $rows = $query->paginate($perPage);
        $rows->getCollection()->transform(function ($item) {
            $total = (int) $item->total_pertemuan;
            $hadir = (int) $item->jumlah_hadir;
            $item->persentase_kehadiran = $total > 0 ? round(($hadir / $total) * 100, 2) : 0;
            $item->rata_menit_terlambat_hadir = $item->rata_menit_terlambat_hadir !== null
                ? round((float) $item->rata_menit_terlambat_hadir, 2)
                : 0;
            return $item;
        });

        return response()->json($rows);
    }

    /**
     * Admin buka atau ambil sesi berdasarkan jadwal dan tanggal.
     */
    public function adminBukaSesi(Request $request): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'id_jadwal' => ['required', 'integer', 'exists:jadwal_pembelajaran,id_jadwal'],
            'tanggal' => ['nullable', 'string'],
            'id_petugas_hadir' => ['nullable', 'integer', 'exists:data_petugas,id_petugas'],
            'status_kehadiran' => ['nullable', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT'])],
            'menit_terlambat' => ['nullable', 'integer', 'min:0'],
            'catat_absensi_pengajar' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ]);

        $jadwal = JadwalPembelajaran::with('kelasMapel')->findOrFail((int) $validated['id_jadwal']);
        $tanggalAcuan = $this->resolveTanggalSesi($validated['tanggal'] ?? null);
        $tanggal = $this->sesuaikanTanggalKeHariJadwal($tanggalAcuan, (string) ($jadwal->hari ?? ''));
        $catatAbsensiPengajar = array_key_exists('catat_absensi_pengajar', $validated)
            ? (bool) $validated['catat_absensi_pengajar']
            : true;
        $statusKehadiran = strtoupper(trim((string) ($validated['status_kehadiran'] ?? 'HADIR')));
        $menitTerlambatInput = max(0, (int) ($validated['menit_terlambat'] ?? 0));
        $waktuMulai = $this->hitungWaktuMulaiDariTerlambat($tanggal, $jadwal->jam_mulai, $menitTerlambatInput);
        $waktuSelesai = $jadwal->jam_selesai ?: now(config('app.timezone'))->format('H:i:s');

        $existingSesi = SesiAbsensi::query()
            ->where('id_jadwal', (int) $validated['id_jadwal'])
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($existingSesi) {
            $existingSesi->waktu_mulai = $waktuMulai;
            $existingSesi->waktu_selesai = $waktuSelesai;
            $existingSesi->status_sesi = 'SELESAI';
            if (array_key_exists('keterangan', $validated)) {
                $existingSesi->keterangan = $validated['keterangan'];
            }
            $existingSesi->save();

            if ($catatAbsensiPengajar && (int) ($existingSesi->id_petugas_hadir ?? 0) > 0) {
                $menitTerlambat = $validated['menit_terlambat']
                    ?? $this->hitungMenitTerlambat(
                        (string) $existingSesi->tanggal,
                        $jadwal->jam_mulai,
                        (string) ($existingSesi->waktu_mulai ?? $jadwal->jam_mulai),
                        $statusKehadiran
                    );

                AbsensiPengajar::query()->updateOrCreate(
                    [
                        'id_sesi' => (int) $existingSesi->id_sesi,
                        'id_petugas' => (int) $existingSesi->id_petugas_hadir,
                        'tanggal' => (string) $existingSesi->tanggal,
                    ],
                    [
                        'status_kehadiran' => $statusKehadiran,
                        'menit_terlambat' => (int) $menitTerlambat,
                        'keterangan' => $validated['keterangan'] ?? null,
                        'input_oleh' => (int) $admin->id_petugas,
                    ]
                );
            }

            return response()->json([
                'message' => 'Sesi untuk jadwal dan tanggal ini sudah ada.',
                'data' => $this->loadSesi((int) $existingSesi->id_sesi),
            ]);
        }

        $idPetugasHadir = isset($validated['id_petugas_hadir'])
            ? (int) $validated['id_petugas_hadir']
            : (int) ($jadwal->kelasMapel?->id_petugas ?? 0);

        if ($idPetugasHadir <= 0) {
            throw ValidationException::withMessages([
                'id_petugas_hadir' => ['Petugas hadir wajib diisi jika jadwal belum memiliki petugas.'],
            ]);
        }

        $sesi = DB::transaction(function () use ($jadwal, $tanggal, $validated, $idPetugasHadir, $waktuMulai, $catatAbsensiPengajar, $statusKehadiran, $admin) {
            $sesi = SesiAbsensi::create([
                'id_jadwal' => (int) $jadwal->id_jadwal,
                'id_petugas_hadir' => $idPetugasHadir,
                'tanggal' => $tanggal,
                'waktu_mulai' => $waktuMulai,
                'waktu_selesai' => $jadwal->jam_selesai ?: now(config('app.timezone'))->format('H:i:s'),
                'status_sesi' => 'SELESAI',
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

        return response()->json([
            'message' => 'Sesi absensi berhasil dibuka oleh admin.',
            'data' => $this->loadSesi((int) $sesi->id_sesi),
        ], 201);
    }

    /**
     * Admin tambah/edit absensi petugas pada sesi tertentu.
     */
    public function adminUpsertAbsensiPengajar(Request $request, int $id): JsonResponse
    {
        $admin = $this->resolveCurrentPetugas($request);
        $this->authorizeAdmin($admin);

        $validated = $request->validate([
            'id_petugas' => ['required', 'integer', 'exists:data_petugas,id_petugas'],
            'status_kehadiran' => ['required', 'string', Rule::in(['HADIR', 'IZIN', 'SAKIT'])],
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

    /**
     * Admin hapus absensi petugas pada sesi tertentu.
     */
    public function adminDeleteAbsensiPengajar(Request $request, int $id): JsonResponse
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

    /**
     * Admin tambah/edit absensi santri pada sesi tertentu.
     */
    public function adminUpsertAbsensiSantri(Request $request, int $id): JsonResponse
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

    /**
     * Admin hapus absensi santri pada sesi tertentu.
     */
    public function adminDeleteAbsensiSantri(Request $request, int $id): JsonResponse
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

    private function loadSesi(int $id): SesiAbsensi
    {
        return SesiAbsensi::with([
            'jadwal.kelasMapel.kelas',
            'jadwal.kelasMapel.mataPelajaran',
            'jadwal.kelasMapel.petugas',
            'petugasHadir',
            'petugasPengganti',
            'absensiPengajar.petugas',
            'absensiSantri.santri',
        ])->findOrFail($id);
    }

    private function resolveCurrentPetugas(Request $request): DataPetugas
    {
        $petugas = auth('petugas')->user();

        if (!$petugas instanceof DataPetugas) {
            $user = $request->user();
            if ($user instanceof DataPetugas) {
                $petugas = $user;
            }
        }

        if (!$petugas instanceof DataPetugas) {
            abort(403, 'Akses khusus petugas.');
        }

        return $petugas;
    }

    private function authorizeAdmin(DataPetugas $petugas): void
    {
        if ((string) $petugas->peran_akun !== 'Petugas Admin') {
            abort(403, 'Akses khusus Petugas Admin.');
        }
    }

    private function hitungMenitTerlambat(string $tanggal, ?string $jamMulaiJadwal, ?string $waktuMulaiAktual, string $statusKehadiran): int
    {
        if ($statusKehadiran !== 'HADIR' || empty($jamMulaiJadwal) || empty($waktuMulaiAktual)) {
            return 0;
        }

        $timezone = config('app.timezone', 'Asia/Jakarta');

        try {
            $jadwalTs = Carbon::parse($tanggal . ' ' . $jamMulaiJadwal, $timezone);
            $mulaiTs = Carbon::parse($tanggal . ' ' . $waktuMulaiAktual, $timezone);
        } catch (\Throwable $exception) {
            return 0;
        }

        if ($mulaiTs->lessThanOrEqualTo($jadwalTs)) {
            return 0;
        }

        return $jadwalTs->diffInMinutes($mulaiTs);
    }

    private function hitungWaktuMulaiDariTerlambat(string $tanggal, ?string $jamMulaiJadwal, int $menitTerlambat): string
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');

        if (empty($jamMulaiJadwal)) {
            return now($timezone)->format('H:i:s');
        }

        try {
            $jadwalTs = Carbon::parse($tanggal . ' ' . $jamMulaiJadwal, $timezone);
        } catch (\Throwable $exception) {
            return now($timezone)->format('H:i:s');
        }

        if ($menitTerlambat > 0) {
            $jadwalTs->addMinutes($menitTerlambat);
        }

        return $jadwalTs->format('H:i:s');
    }

    private function resolveTanggalSesi(?string $tanggalInput): string
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');

        if ($tanggalInput === null || trim($tanggalInput) === '') {
            return now($timezone)->toDateString();
        }

        $tanggalInput = trim($tanggalInput);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalInput) === 1) {
            return $tanggalInput;
        }

        try {
            return Carbon::parse($tanggalInput)->timezone($timezone)->toDateString();
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'tanggal' => ['Format tanggal tidak valid. Gunakan format Y-m-d atau ISO datetime.'],
            ]);
        }
    }

    private function sesuaikanTanggalKeHariJadwal(string $tanggal, string $hariJadwal): string
    {
        $hariJadwal = strtoupper(trim($hariJadwal));

        if ($hariJadwal === '') {
            return $tanggal;
        }

        $hariTarget = match ($hariJadwal) {
            'SENIN' => 1,
            'SELASA' => 2,
            'RABU' => 3,
            'KAMIS' => 4,
            'JUMAT' => 5,
            'SABTU' => 6,
            'MINGGU' => 7,
            default => 0,
        };

        if ($hariTarget <= 0) {
            return $tanggal;
        }

        $timezone = config('app.timezone', 'Asia/Jakarta');

        try {
            $carbon = Carbon::parse($tanggal, $timezone);
        } catch (
            \Throwable $exception
        ) {
            return $tanggal;
        }

        $hariSaatIni = (int) $carbon->dayOfWeekIso;
        $selisih = $hariTarget - $hariSaatIni;

        return $carbon->addDays($selisih)->toDateString();
    }

    private function resolveHariIndonesia(string $tanggal): string
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');

        try {
            $dayOfWeek = Carbon::parse($tanggal, $timezone)->dayOfWeekIso;
        } catch (\Throwable $exception) {
            return '';
        }

        return match ($dayOfWeek) {
            1 => 'SENIN',
            2 => 'SELASA',
            3 => 'RABU',
            4 => 'KAMIS',
            5 => 'JUMAT',
            6 => 'SABTU',
            7 => 'MINGGU',
            default => '',
        };
    }

    private function isWaktuDalamRentangJadwal(string $tanggal, string $waktuMulaiRealtime, ?string $jamMulaiJadwal, ?string $jamSelesaiJadwal): bool
    {
        if (empty($jamMulaiJadwal) || empty($jamSelesaiJadwal)) {
            return true;
        }

        $timezone = config('app.timezone', 'Asia/Jakarta');

        try {
            $mulaiTs = Carbon::parse($tanggal . ' ' . $waktuMulaiRealtime, $timezone);
            $jadwalMulaiTs = Carbon::parse($tanggal . ' ' . $jamMulaiJadwal, $timezone);
            $jadwalSelesaiTs = Carbon::parse($tanggal . ' ' . $jamSelesaiJadwal, $timezone);
        } catch (\Throwable $exception) {
            return true;
        }

        if ($jadwalSelesaiTs->lessThan($jadwalMulaiTs)) {
            return true;
        }

        return $mulaiTs->betweenIncluded($jadwalMulaiTs, $jadwalSelesaiTs);
    }
}
