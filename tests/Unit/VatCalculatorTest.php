<?php
namespace Tests\Unit;

use App\Services\VatCalculatorService;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    public function test_ddp_applies_ten_percent(): void
    {
        $svc = new VatCalculatorService();
        $r = $svc->calculate(1000, 'DDP');
        $this->assertEquals(10.0, (float)$r['vat_percent']);
        $this->assertEquals(100.0, (float)$r['vat_amount']);
        $this->assertEquals(1100.0, (float)$r['gross_amount']);
    }

    public function test_cpt_no_vat(): void
    {
        $svc = new VatCalculatorService();
        $r = $svc->calculate(1000, 'CPT');
        $this->assertEquals(0.0, (float)$r['vat_percent']);
        $this->assertEquals(1000.0, (float)$r['gross_amount']);
    }
}
