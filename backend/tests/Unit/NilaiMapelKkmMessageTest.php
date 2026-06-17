<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Akademik\NilaiMapelController;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NilaiMapelKkmMessageTest extends TestCase
{
    #[Test]
    public function pesan_kkm_yang_hilang_menyebut_semester_target_dan_semester_tersedia(): void
    {
        $controller = new NilaiMapelController();

        $method = new \ReflectionMethod($controller, 'formatMissingKkmMessage');
        $method->setAccessible(true);

        /** @var string $message */
        $message = $method->invoke($controller, 2, [1]);

        $this->assertSame(
            'KKM mapel untuk semester 2 belum tersedia. Silakan buat KKM semester 2 terlebih dahulu. Semester yang sudah tersedia: 1.',
            $message
        );
    }

    #[Test]
    public function pesan_kkm_yang_hilang_tetap_jelas_meski_belum_ada_semester_lain(): void
    {
        $controller = new NilaiMapelController();

        $method = new \ReflectionMethod($controller, 'formatMissingKkmMessage');
        $method->setAccessible(true);

        /** @var string $message */
        $message = $method->invoke($controller, 2, []);

        $this->assertSame(
            'KKM mapel untuk semester 2 belum tersedia. Silakan buat KKM semester 2 terlebih dahulu.',
            $message
        );
    }
}
