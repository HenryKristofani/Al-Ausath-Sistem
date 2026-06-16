<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Akademik\RaportPdfController;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaportPdfNilaiAkhlakTest extends TestCase
{
    #[Test]
    public function ringkasan_nilai_akhlak_menggunakan_deskripsi_saja(): void
    {
        $controller = new RaportPdfController();

        $method = new \ReflectionMethod($controller, 'buildNilaiAkhlakRingkas');
        $method->setAccessible(true);

        $nilaiAkhlak = new Collection([
            (object) [
                'aspek' => 'AKHLAK',
                'nilai_angka' => 90,
                'deskripsi' => 'Sangat baik',
            ],
            (object) [
                'aspek' => 'KEPRIBADIAN',
                'nilai_angka' => 88.5,
                'deskripsi' => 'Baik',
            ],
        ]);

        /** @var array{label:string, angka:?float, huruf:?string, keterangan:?string, detail:string}|null $result */
        $result = $method->invoke($controller, $nilaiAkhlak);

        $this->assertNotNull($result);
        $this->assertSame('Akhlaq', $result['label']);
        $this->assertSame(89.25, $result['angka']);
        $this->assertSame('', $result['huruf']);
        $this->assertSame('Sangat baik; Baik', $result['detail']);
    }

    #[Test]
    public function nilai_keseluruhan_menggabungkan_mapel_dan_akhlak(): void
    {
        $controller = new RaportPdfController();

        $method = new \ReflectionMethod($controller, 'calculateNilaiKeseluruhan');
        $method->setAccessible(true);

        $nilaiMapel = new Collection([
            (object) ['nilai_rapor_tampil' => 80],
            (object) ['nilai_rapor_tampil' => 90],
        ]);

        /** @var array{jumlah_nilai: float, rata_rata_nilai: float, jumlah_komponen: int} $result */
        $result = $method->invoke($controller, $nilaiMapel, [
            'angka' => 85.5,
        ]);

        $this->assertSame(255.5, $result['jumlah_nilai']);
        $this->assertSame(85.17, $result['rata_rata_nilai']);
        $this->assertSame(3, $result['jumlah_komponen']);
    }
}
