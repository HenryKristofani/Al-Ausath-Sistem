<?php

namespace App\Http\Controllers\Api\Akademik\Traits;

use App\Models\DataPetugas;
use App\Models\SesiAbsensi;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait SesiAbsensiHelpers
{
    protected function loadSesi(int $id): SesiAbsensi
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

    protected function resolveCurrentPetugas(Request $request): DataPetugas
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

    protected function authorizeAdmin(DataPetugas $petugas): void
    {
        $userRole = $petugas->peran_akun;
        
        if (is_string($userRole) && str_starts_with($userRole, '[')) {
            $decoded = json_decode($userRole, true);
            if (is_array($decoded)) {
                $userRole = $decoded;
            }
        }

        $rolesToCheck = is_array($userRole) ? $userRole : [(string) $userRole];
        $rolesToCheck = array_map(fn($r) => trim((string) $r), $rolesToCheck);

        if (!in_array('Petugas Admin', $rolesToCheck, true)) {
            abort(403, 'Akses khusus Petugas Admin.');
        }
    }

    protected function hitungMenitTerlambat(string $tanggal, ?string $jamMulaiJadwal, ?string $waktuMulaiAktual, string $statusKehadiran): int
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

    protected function resolveTanggalSesi(?string $tanggalInput): string
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

    protected function resolveHariIndonesia(string $tanggal): string
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

    protected function isWaktuDalamRentangJadwal(string $tanggal, string $waktuMulaiRealtime, ?string $jamMulaiJadwal, ?string $jamSelesaiJadwal): bool
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

    protected function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? (string) $exception->getCode();

        return in_array((string) $sqlState, ['23505', '23000'], true);
    }
}
