<?php

namespace App\Support;

use App\Models\DataSantri;
use App\Models\PpdbPendaftar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PpdbRegistrationNumberService
{
    public function generatePendaftaranId(?Carbon $tanggalDaftar = null): int
    {
        $tanggal = ($tanggalDaftar ?? now())->copy();
        $tahun = (int) $tanggal->format('Y');

        // Format ID: YYYYNNN (contoh: 2026123).
        $base = $tahun * 1000;
        $minId = $base + 1;
        $maxId = $base + 999;

        $idTerakhir = (int) (PpdbPendaftar::query()
            ->whereBetween('id_pendaftaran', [$minId, $maxId])
            ->max('id_pendaftaran') ?? 0);

        $nomorUrut = $idTerakhir >= $minId
            ? ($idTerakhir - $base) + 1
            : 1;

        while ($nomorUrut <= 999) {
            $candidate = $base + $nomorUrut;

            if (!PpdbPendaftar::query()->where('id_pendaftaran', $candidate)->exists()) {
                return $candidate;
            }

            $nomorUrut++;
        }

        throw new \RuntimeException('ID pendaftaran tahun ini sudah penuh.');
    }

    public function generateInitialNumber(?Carbon $tanggalDaftar = null): string
    {
        $tanggal = ($tanggalDaftar ?? now())->copy();
        $prefix = 'PPDB-' . $tanggal->format('Ymd') . '-';

        $lastNumber = PpdbPendaftar::query()
            ->whereDate('tanggal_daftar', $tanggal->toDateString())
            ->where('no_pendaftaran', 'like', $prefix . '%')
            ->orderByDesc('id_pendaftaran')
            ->value('no_pendaftaran');

        $counter = 1;

        if ($lastNumber) {
            $parts = explode('-', (string) $lastNumber);
            $tail = end($parts);

            if (is_string($tail) && ctype_digit($tail)) {
                $counter = ((int) $tail) + 1;
            }
        }

        do {
            $candidate = $prefix . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            $counter++;
        } while (
            PpdbPendaftar::query()->where('no_pendaftaran', $candidate)->exists()
        );

        return $candidate;
    }

    public function generateFinalNumber(string $initialNumber): string
    {
        do {
            $candidate = $initialNumber . '-' . strtoupper(Str::random(4));
        } while (
            PpdbPendaftar::query()->where('no_pendaftaran_final', $candidate)->exists()
        );

        return $candidate;
    }

    public function generateNomorIndukFromPendaftaran(PpdbPendaftar $pendaftar): string
    {
        $tanggal = $pendaftar->tanggal_daftar
            ? Carbon::parse($pendaftar->tanggal_daftar)
            : now();

        $counterPart = $this->extractCounterFromRegistration($pendaftar->no_pendaftaran);
        $suffixPart = $this->extractSuffixFromFinalRegistration($pendaftar->no_pendaftaran_final);

        $base = 'NIS'
            . $tanggal->format('ymd')
            . str_pad((string) $counterPart, 4, '0', STR_PAD_LEFT)
            . $suffixPart;

        $candidate = mb_substr($base, 0, 20);
        $attempt = 0;

        while ($this->isNomorIndukUsed($candidate, $pendaftar->id_pendaftaran)) {
            $attempt++;
            $tail = str_pad((string) $attempt, 2, '0', STR_PAD_LEFT);
            $candidate = mb_substr($base, 0, max(20 - mb_strlen($tail), 1)) . $tail;
        }

        return $candidate;
    }

    public function generateNomorIndukAfterPayment(PpdbPendaftar $pendaftar, ?int $tahunMasuk = null): string
    {
        $tahun = $tahunMasuk
            ?: (int) ($pendaftar->tanggal_daftar ? Carbon::parse($pendaftar->tanggal_daftar)->format('Y') : now()->format('Y'));

        $segmentJenjang = $this->normalizeJenjangSegment($pendaftar->jenjang ?: $pendaftar->program_pendaftaran);
        $prefix = $segmentJenjang . '/' . $tahun;

        $counter = 1;

        while ($counter <= 9999) {
            $candidate = $prefix . '/' . str_pad((string) $counter, 3, '0', STR_PAD_LEFT);

            if (!$this->isNomorIndukUsed($candidate, $pendaftar->id_pendaftaran)) {
                return $candidate;
            }

            $counter++;
        }

        throw new \RuntimeException('Nomor induk untuk jenjang dan tahun masuk ini sudah penuh.');
    }

    public function isLuarKota(?string $asalKota, ?bool $override = null): bool
    {
        if ($override !== null) {
            return $override;
        }

        if ($asalKota === null || trim($asalKota) === '') {
            return false;
        }

        return mb_strtolower(trim($asalKota)) !== 'karanganyar';
    }

    private function extractCounterFromRegistration(?string $registrationNumber): int
    {
        if (!$registrationNumber) {
            return 0;
        }

        $parts = explode('-', $registrationNumber);
        $counter = end($parts);

        if (is_string($counter) && ctype_digit($counter)) {
            return (int) $counter;
        }

        return 0;
    }

    private function extractSuffixFromFinalRegistration(?string $finalNumber): string
    {
        if (!$finalNumber) {
            return 'AAA';
        }

        $parts = explode('-', $finalNumber);
        $suffix = (string) end($parts);
        $letters = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $suffix));

        if ($letters === '') {
            return 'AAA';
        }

        return mb_substr($letters, 0, 3);
    }

    private function normalizeJenjangSegment(?string $jenjang): string
    {
        $raw = mb_strtolower(trim((string) $jenjang));

        if ($raw === '') {
            return 'UMUM';
        }

        $map = [
            'paud' => 'PAUD',
            'paud1' => 'PAUD1',
            'paud 1' => 'PAUD1',
            'paud2' => 'PAUD2',
            'paud 2' => 'PAUD2',
            'paud3' => 'PAUD3',
            'paud 3' => 'PAUD3',
            'paud4' => 'PAUD4',
            'paud 4' => 'PAUD4',
            'paud5' => 'PAUD5',
            'paud 5' => 'PAUD5',
            'sd' => 'SD',
            'smp' => 'SMP',
            'sma' => 'SMA',
        ];

        if (isset($map[$raw])) {
            return $map[$raw];
        }

        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));

        return $normalized !== '' ? $normalized : 'UMUM';
    }

    private function isNomorIndukUsed(string $nomorInduk, ?int $idPendaftaran): bool
    {
        $usedBySantri = DataSantri::query()
            ->where('nomor_induk', $nomorInduk)
            ->exists();

        if ($usedBySantri) {
            return true;
        }

        if (!Schema::hasColumn('ppdb_pendaftar', 'nomor_induk_generated')) {
            return false;
        }

        return PpdbPendaftar::query()
            ->where('nomor_induk_generated', $nomorInduk)
            ->when($idPendaftaran, fn ($q) => $q->where('id_pendaftaran', '!=', $idPendaftaran))
            ->exists();
    }
}
