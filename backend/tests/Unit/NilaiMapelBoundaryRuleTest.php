<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Akademik\NilaiMapelController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NilaiMapelBoundaryRuleTest extends TestCase
{
    #[Test]
    public function nilai_empat_puluh_sembilan_koma_x_ditampilkan_lima_puluh_merah(): void
    {
        $controller = new NilaiMapelController();

        $nilaiMentah = 49.6;
        $nilaiBulat = $this->invokeRoundRaporInteger($controller, $nilaiMentah);
        [$nilaiTampil, $warna] = $this->invokeNormalizeNilaiRapor($controller, $nilaiMentah, $nilaiBulat);

        $this->assertSame(50, $nilaiTampil);
        $this->assertSame('MERAH', $warna);
    }

    #[Test]
    public function nilai_lima_puluh_asli_ditampilkan_lima_puluh_hitam(): void
    {
        $controller = new NilaiMapelController();

        $nilaiMentah = 50.0;
        $nilaiBulat = $this->invokeRoundRaporInteger($controller, $nilaiMentah);
        [$nilaiTampil, $warna] = $this->invokeNormalizeNilaiRapor($controller, $nilaiMentah, $nilaiBulat);

        $this->assertSame(50, $nilaiTampil);
        $this->assertSame('HITAM', $warna);
    }

    #[Test]
    public function nilai_seratus_dibatasi_jadi_sembilan_puluh_delapan_hitam(): void
    {
        $controller = new NilaiMapelController();

        $nilaiMentah = 100.0;
        $nilaiBulat = $this->invokeRoundRaporInteger($controller, $nilaiMentah);
        [$nilaiTampil, $warna] = $this->invokeNormalizeNilaiRapor($controller, $nilaiMentah, $nilaiBulat);

        $this->assertSame(98, $nilaiTampil);
        $this->assertSame('HITAM', $warna);
    }

    private function invokeRoundRaporInteger(NilaiMapelController $controller, float $nilai): int
    {
        $method = new \ReflectionMethod($controller, 'roundRaporInteger');
        $method->setAccessible(true);

        /** @var int $result */
        $result = $method->invoke($controller, $nilai);

        return $result;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function invokeNormalizeNilaiRapor(NilaiMapelController $controller, float $nilaiMentah, int $nilaiBulat): array
    {
        $method = new \ReflectionMethod($controller, 'normalizeNilaiRapor');
        $method->setAccessible(true);

        /** @var array{0:int,1:string} $result */
        $result = $method->invoke($controller, $nilaiMentah, $nilaiBulat);

        return $result;
    }
}
