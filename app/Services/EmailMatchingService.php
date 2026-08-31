<?php
namespace App\Services;

use App\Models\CaseModel;
use App\Models\EmailMessage;

class EmailMatchingService
{
    /** Match subject/body against CASE-000123 pattern */
    public function matchCase(?string $subject, ?string $body = null): ?CaseModel
    {
        $text = ($subject ?? '') . ' ' . ($body ?? '');
        if (preg_match('/\b(CASE[-_]?\d{4,})\b/i', $text, $m)) {
            $num = strtoupper(str_replace('_', '-', $m[1]));
            if (!str_contains($num, '-')) {
                $num = 'CASE-' . preg_replace('/\D/', '', $num);
            }
            return CaseModel::where('case_number', $num)->first()
                ?? CaseModel::where('case_number', 'like', '%' . preg_replace('/\D/', '', $m[1]))->first();
        }
        return null;
    }

    public function storeInbound(array $data): EmailMessage
    {
        $case = $this->matchCase($data['subject'] ?? null, $data['body'] ?? null);
        return EmailMessage::create([
            'case_id' => $case?->id,
            'direction' => 'inbound',
            'from_address' => $data['from_address'],
            'to_address' => $data['to_address'] ?? '',
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'is_linked' => (bool) $case,
        ]);
    }
}
