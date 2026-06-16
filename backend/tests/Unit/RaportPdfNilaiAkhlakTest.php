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
}
