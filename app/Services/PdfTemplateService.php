<?php
namespace App\Services;

class PdfTemplateService
{
    public function __construct(protected TemplateRenderService $renderer) {}

    /** Returns HTML suitable for browser print / PDF */
    public function toHtml(string $header, string $body, string $footer, array $vars): string
    {
        $h = $this->renderer->render($header, $vars);
        $b = $this->renderer->render($body, $vars);
        $f = $this->renderer->render($footer, $vars);
        return '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">'
            .'<style>body{font-family:Tahoma,sans-serif;padding:24px;line-height:1.6}'
            .'.header{border-bottom:2px solid #333;margin-bottom:16px;padding-bottom:8px}'
            .'.footer{border-top:1px solid #999;margin-top:24px;padding-top:8px;font-size:12px;color:#555}'
            .'@media print{button{display:none}}</style></head><body>'
            .'<div class="header">'.$h.'</div>'
            .'<div class="body">'.nl2br(e($b)).'</div>'
            .'<div class="footer">'.$f.'</div>'
            .'<script>window.onload=function(){/* window.print(); */}</script>'
            .'</body></html>';
    }
}
