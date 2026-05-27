<?php

namespace App\Observers;

use App\Models\JadwalPembelajaran;
use App\Models\DataAkunSantri;
use App\Notifications\JadwalBerubah;
use Illuminate\Support\Facades\Notification;

class JadwalPembelajaranObserver
{
    public function updated(JadwalPembelajaran $jadwal): void
    {
        // Only notify when relevant fields changed
        $changes = array_keys($jadwal->getChanges());
        $relevant = array_intersect($changes, ['id_kelas_mapel', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan', 'status']);
        if (empty($relevant)) {
            return;
        }

        $kelasMapel = $jadwal->kelasMapel;
        if (! $kelasMapel) {
            return;
        }

        $kodeKelas = $kelasMapel->kode_kelas;
        if (! $kodeKelas) {
            return;
        }

        // Find santri accounts whose DataSantri.kode_kelas = $kodeKelas
        $users = DataAkunSantri::whereHas('santri', function ($q) use ($kodeKelas) {
            $q->where('kode_kelas', $kodeKelas);
        })->get();

        if ($users->isEmpty()) {
            return;
        }

        $payload = [
            'pesan' => 'Jadwal pembelajaran berubah',
            'jadwal_id' => $jadwal->id_jadwal,
            'nama_mapel' => optional($kelasMapel->mataPelajaran)->nama_mapel ?? null,
            'hari' => $jadwal->hari,
            'jam_mulai' => $jadwal->jam_mulai,
            'jam_selesai' => $jadwal->jam_selesai,
            'ruangan' => $jadwal->ruangan,
            'perubahan' => $relevant,
        ];

        Notification::send($users, new JadwalBerubah($payload));
    }
}
