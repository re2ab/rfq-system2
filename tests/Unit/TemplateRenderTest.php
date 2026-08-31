<?php
namespace Tests\Unit;

use App\Services\TemplateRenderService;
use PHPUnit\Framework\TestCase;

class TemplateRenderTest extends TestCase
{
    public function test_replaces_placeholders(): void
    {
        $svc = new TemplateRenderService();
        $out = $svc->render('Hello {{name}} and {{ name }}', ['name' => 'Ali']);
        $this->assertSame('Hello Ali and Ali', $out);
    }
}
