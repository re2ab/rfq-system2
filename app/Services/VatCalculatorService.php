<?php

namespace App\Services;

class VatCalculatorService
{
    /**
     * Calculate VAT based on Incoterm.
     * CPT / CFR => 0%
     * DDP => 10%
     */
    public function calculate(float $netAmount, ?string $incoterm, ?float $overridePercent = null): array
    {
        $percent = $overridePercent;

        if ($percent === null) {
            $percent = match (strtoupper($incoterm ?? '')) {
                'DDP' => 10.0,
                'CPT', 'CFR' => 0.0,
                default => 0.0,
            };
        }

        $vatAmount = round($netAmount * ($percent / 100), 2);
        $grossAmount = round($netAmount + $vatAmount, 2);

        return [
            'vat_percent' => $percent,
            'net_amount' => $netAmount,
            'vat_amount' => $vatAmount,
            'gross_amount' => $grossAmount,
        ];
    }
}
