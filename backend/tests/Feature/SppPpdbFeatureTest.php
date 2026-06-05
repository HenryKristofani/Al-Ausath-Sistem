<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\DataPetugas;
use App\Models\DataUnit;
use App\Models\DataKelas;
use App\Models\DataSantri;
use App\Models\SppGolongan;
use App\Models\SppSetting;
use App\Models\PembayaranSpp;
use App\Models\PpdbPendaftar;
use App\Models\PpdbPeriod;
use Laravel\Sanctum\Sanctum;

class SppPpdbFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        
        $realDb = 'neondb';
        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $lines = file($envPath);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                    $realDb = trim(explode('=', $line)[1]);
                    $realDb = trim($realDb, "\"'");
                    break;
                }
            }
        }

        $realHost = env('DB_HOST', 'ep-snowy-grass-ao967lpg-pooler.c-2.ap-southeast-1.aws.neon.tech');
        $realOptions = env('DB_OPTIONS');
        if ($realOptions) {
            $realHost .= ';options=' . $realOptions;
        }

        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql.database' => $realDb,
            'database.connections.pgsql.host' => $realHost,
            'database.connections.pgsql.username' => env('DB_USERNAME', 'neondb_owner'),
            'database.connections.pgsql.password' => env('DB_PASSWORD', 'npg_LMSTrxpb8yC6'),
        ]);
    }

    private function createMockPendaftar(array $attributes = []): PpdbPendaftar
    {
        $id = rand(800000, 899999);
        while (PpdbPendaftar::where('id_pendaftaran', $id)->exists()) {
            $id = rand(800000, 899999);
        }

        $noPendaftar = 'PPDB-MOCK-' . rand(1000, 9999) . '-' . uniqid();

        $pendaftar = new PpdbPendaftar(array_merge([
            'nama_calon' => 'Calon Mock',
            'status_verifikasi' => 'pending',
            'no_pendaftaran' => $noPendaftar,
        ], $attributes));

        $pendaftar->id_pendaftaran = $id;
        $pendaftar->save();

        return $pendaftar;
    }

    /**
     * FR-ADM-01.1: Tambah, Ubah, Hapus Setting SPP
     */
    public function test_fr_adm_01_1_spp_setting(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $unit = DataUnit::first();
        $kelas = DataKelas::first();

        // 1. Valid Creation
        $response = $this->postJson('/api/administrasi/spp/setting', [
            'id_unit' => $unit?->id_unit,
            'kode_kelas' => $kelas?->kode_kelas,
            'jenjang' => 'SMP',
            'jumlah' => 150000,
            'periode' => '2026/2027',
            'keterangan' => 'Tagihan SPP Bulanan Kelas 7',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'data']);

        $idSetting = $response->json('data.id_setting');

        // 2. Validation Fail (422) - missing target attributes
        $failResponse = $this->postJson('/api/administrasi/spp/setting', [
            'jumlah' => 150000,
        ]);
        $failResponse->assertStatus(422);

        // 3. Update Setting
        $updateResponse = $this->putJson("/api/administrasi/spp/setting/{$idSetting}", [
            'jumlah' => 200000,
            'keterangan' => 'Tagihan SPP Bulanan Kelas 7 Terupdate',
        ]);
        $updateResponse->assertStatus(200);
        $this->assertEquals(200000, $updateResponse->json('data.jumlah'));

        // 4. Delete Setting
        $deleteResponse = $this->deleteJson("/api/administrasi/spp/setting/{$idSetting}");
        $deleteResponse->assertStatus(200);
    }

    /**
     * FR-ADM-01.2: Tambah & Ubah Golongan SPP
     */
    public function test_fr_adm_01_2_spp_golongan(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $golonganA = 'Golongan A ' . uniqid();
        $golonganB = 'Golongan B ' . uniqid();

        // 1. Valid Creation
        $response = $this->postJson('/api/administrasi/spp/golongan', [
            'nama_golongan' => $golonganA,
            'jenjang' => 'SMP',
            'nominal' => 250000,
            'is_aktif' => true,
            'keterangan' => 'Golongan reguler SMP',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'data']);

        $idGolongan = $response->json('data.id_golongan');

        // 2. Validation Fail (422) - nominal negative
        $failResponse = $this->postJson('/api/administrasi/spp/golongan', [
            'nama_golongan' => $golonganB,
            'jenjang' => 'SMP',
            'nominal' => -5000,
        ]);
        $failResponse->assertStatus(422);

        // 3. Update (aktif/nonaktif)
        $updateResponse = $this->putJson("/api/administrasi/spp/golongan/{$idGolongan}", [
            'is_aktif' => false,
        ]);
        $updateResponse->assertStatus(200)
                       ->assertJsonPath('data.is_aktif', false);
    }

    /**
     * FR-ADM-01.3: Pembuatan Tagihan SPP
     */
    public function test_fr_adm_01_3_spp_pembayaran_creation(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $santri = DataSantri::first();
        $setting = SppSetting::first();

        // 1. Valid Creation for Santri
        if ($santri) {
            $response = $this->postJson('/api/administrasi/spp/pembayaran', [
                'id_santri' => $santri->id_santri,
                'id_setting' => $setting?->id_setting,
                'nominal_bayar' => 150000,
                'metode_bayar' => 'transfer',
                'status' => 'menunggu_verifikasi',
            ]);

            $response->assertStatus(201);
        }

        // 2. Validation Fail (422) - missing target identity
        $failResponse = $this->postJson('/api/administrasi/spp/pembayaran', [
            'nominal_bayar' => 150000,
        ]);
        $failResponse->assertStatus(422);
    }

    /**
     * FR-ADM-01.4: Verifikasi Pembayaran SPP & Kwitansi
     */
    public function test_fr_adm_01_4_spp_pembayaran_verification(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $santri = DataSantri::first();
        $setting = SppSetting::first();

        if ($santri) {
            $pembayaran = PembayaranSpp::create([
                'id_santri' => $santri->id_santri,
                'id_setting' => $setting?->id_setting,
                'nominal_bayar' => 100000,
                'status' => 'menunggu_verifikasi',
            ]);

            // 1. Valid verification
            $response = $this->putJson("/api/administrasi/spp/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", [
                'status' => 'terverifikasi',
                'id_petugas_verifikator' => $petugas->id_petugas,
            ]);
            $response->assertStatus(200)
                     ->assertJsonStructure(['message', 'data' => ['pembayaran', 'kwitansi']]);

            // Verify kwitansi is generated
            $this->assertDatabaseHas('kwitansi_pdf', [
                'id_pembayaran' => $pembayaran->id_pembayaran,
            ]);

            // 2. Invalid status (422)
            $failResponse = $this->putJson("/api/administrasi/spp/pembayaran/{$pembayaran->id_pembayaran}/verifikasi", [
                'status' => 'status_tidak_valid',
            ]);
            $failResponse->assertStatus(422);
        }
    }

    /**
     * FR-ADM-01.6: Monitoring Tunggakan SPP
     */
    public function test_fr_adm_01_6_spp_tunggakan_monitoring(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $response = $this->getJson('/api/administrasi/spp/tunggakan-ringkasan');
        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'meta']);
    }

    /**
     * FR-ADM-02.1: Registrasi Akun Pendaftar PPDB
     */
    public function test_fr_adm_02_1_ppdb_register(): void
    {
        // Create active PPDB Period
        $period = PpdbPeriod::create([
            'nama_gelombang' => 'Gelombang Test',
            'tahun_ajaran' => '2026/2027',
            'tanggal_mulai' => now()->subDay()->toDateString(),
            'tanggal_selesai' => now()->addDay()->toDateString(),
            'kuota' => 100,
            'biaya_pendaftaran' => 100000,
            'status' => 'aktif',
        ]);

        $email = 'testcandidate_' . uniqid() . '@example.com';
        $response = $this->postJson('/api/ppdb/register', [
            'nama' => 'Calon Santri Baru',
            'email_ppdb' => $email,
            'phone_ppdb' => '081234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'role', 'user', 'pendaftaran']);
    }

    /**
     * FR-ADM-02.3: Unggah Berkas PPDB
     */
    public function test_fr_adm_02_3_ppdb_upload_berkas(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $pendaftar = $this->createMockPendaftar([
            'nama_calon' => 'Calon Luar Kota',
            'is_luar_kota' => true,
        ]);

        $response = $this->postJson("/api/administrasi/ppdb/pendaftar/{$pendaftar->id_pendaftaran}/berkas", [
            'jenis_berkas' => 'akta',
            'file_path' => 'ppdb/berkas/mock_akta.jpg',
            'uploaded_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201);
    }

    /**
     * FR-ADM-02.4: Input/Koreksi Hasil Tes PPDB
     */
    public function test_fr_adm_02_4_ppdb_koreksi_tes(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $pendaftar = $this->createMockPendaftar([
            'nama_calon' => 'Calon Tes',
        ]);

        $response = $this->putJson("/api/administrasi/ppdb/pendaftar/{$pendaftar->id_pendaftaran}/tes", [
            'nilai' => 85.5,
            'status_tes' => 'lulus',
            'metode_tes' => 'online',
            'catatan' => 'Hasil tes sangat baik',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'data']);
    }

    /**
     * FR-ADM-02.5: Verifikasi Hasil PPDB
     */
    public function test_fr_adm_02_5_ppdb_verification(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $kelas = DataKelas::first();

        $pendaftar = $this->createMockPendaftar([
            'nama_calon' => 'Calon Verif',
            'jenjang' => 'SMP',
        ]);

        // 1. Ditolak (422) karena pembayaran administrasi belum lunas
        $response422 = $this->putJson("/api/administrasi/ppdb/pendaftar/{$pendaftar->id_pendaftaran}/verifikasi", [
            'hasil' => 'diterima',
            'kode_kelas_diterima' => $kelas?->kode_kelas,
        ]);
        $response422->assertStatus(422)
                    ->assertJsonFragment(['message' => 'Pendaftar belum bisa diterima. Pembayaran administrasi PPDB belum lunas atau belum diverifikasi.']);

        // 2. Tambahkan pembayaran administrasi yang sudah terverifikasi
        PembayaranSpp::create([
            'id_pendaftaran' => $pendaftar->id_pendaftaran,
            'nominal_bayar' => 100000,
            'status' => 'terverifikasi',
        ]);

        // 3. Verifikasi "diterima" sekarang harus berhasil (200)
        $responseSuccess = $this->putJson("/api/administrasi/ppdb/pendaftar/{$pendaftar->id_pendaftaran}/verifikasi", [
            'hasil' => 'diterima',
            'kode_kelas_diterima' => $kelas?->kode_kelas,
            'integrasikan_langsung_ke_santri' => true,
        ]);
        
        $responseSuccess->assertStatus(200);
    }

    /**
     * FR-ADM-02.7: Pembuatan Tagihan PPDB
     */
    public function test_fr_adm_02_7_ppdb_create_tagihan(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        // 1. Calon pendaftar berstatus pending ditolak pembuatan tagihannya (422)
        $pendaftarPending = $this->createMockPendaftar([
            'nama_calon' => 'Calon Tagihan Pending',
        ]);

        $failResponse = $this->postJson("/api/administrasi/ppdb/pendaftar/{$pendaftarPending->id_pendaftaran}/tagihan", [
            'nominal_bayar' => 150000,
        ]);
        $failResponse->assertStatus(422);

        // 2. Calon pendaftar berstatus diterima sukses (201)
        $pendaftarAccepted = $this->createMockPendaftar([
            'nama_calon' => 'Calon Tagihan Accepted',
            'status_verifikasi' => 'diterima',
        ]);

        $successResponse = $this->postJson("/api/administrasi/ppdb/pendaftar/{$pendaftarAccepted->id_pendaftaran}/tagihan", [
            'nominal_bayar' => 150000,
        ]);
        $successResponse->assertStatus(201);
    }

    /**
     * FR-ADM-02.8: Ekspor Rekap PPDB ke CSV
     */
    public function test_fr_adm_02_8_ppdb_export(): void
    {
        $petugas = DataPetugas::first() ?: DataPetugas::factory()->create();
        Sanctum::actingAs($petugas, ['*']);

        $response = $this->get("/api/administrasi/ppdb/pendaftar/export?status_verifikasi=diterima");
        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
