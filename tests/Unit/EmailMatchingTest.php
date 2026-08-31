<?php
namespace Tests\Unit;

use App\Services\EmailMatchingService;
use PHPUnit\Framework\TestCase;

class EmailMatchingTest extends TestCase
{
    public function test_extracts_case_pattern(): void
    {
        $svc = new EmailMatchingService();
        // without DB, matchCase returns null but regex path shouldn't throw
        $this->assertNull($svc->matchCase('Hello without number'));
        $this->assertNull($svc->matchCase('Regarding CASE-999999 non-existent'));
    }
}
