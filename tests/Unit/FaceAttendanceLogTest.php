<?php

namespace Tests\Unit;

use App\Http\Controllers\FaceAttendanceLogController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FaceAttendanceLogTest extends TestCase
{
    private function controller(): FaceAttendanceLogController
    {
        return new FaceAttendanceLogController();
    }

    public function test_normalize_pin_numeric_column(): void
    {
        $c = $this->controller();
        $m = new ReflectionMethod($c, 'normalizePin');
        $m->setAccessible(true);

        $this->assertSame('123', $m->invoke($c, ' 123.0 '));
        $this->assertSame('ABC-01', $m->invoke($c, ' ABC-01 '));
        $this->assertSame('', $m->invoke($c, null));
    }

    public function test_resolve_date_invalid_falls_back(): void
    {
        $c = $this->controller();
        $m = new ReflectionMethod($c, 'resolveDate');
        $m->setAccessible(true);

        $this->assertSame('2026-11-09', $m->invoke($c, '2026-11-09', '2026-11-01'));
        $this->assertSame('2026-11-01', $m->invoke($c, 'bukan-tanggal', '2026-11-01'));
        $this->assertSame('2026-11-01', $m->invoke($c, '', '2026-11-01'));
    }
}
