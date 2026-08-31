<?php
namespace App\Services;

class TemplateRenderService
{
    public function render(string $template, array $vars): string
    {
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{{'.$key.'}}', (string)$value, $out);
            $out = str_replace('{{ '.$key.' }}', (string)$value, $out);
        }
        return $out;
    }
}
