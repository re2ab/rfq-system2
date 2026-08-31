<?php
namespace App\Services;

/**
 * Official placeholder catalog for RFQ document/email templates.
 * Use in templates as {{key}} or {{ key }}.
 */
class PlaceholderLibrary
{
    public static function all(): array
    {
        return [
            'company' => [
                'label' => 'شرکت',
                'items' => [
                    'company_name' => 'نام شرکت',
                    'company_address' => 'آدرس شرکت',
                    'company_phone' => 'تلفن شرکت',
                    'company_email' => 'ایمیل شرکت',
                    'today' => 'تاریخ امروز',
                    'now' => 'تاریخ و زمان الان',
                ],
            ],
            'case' => [
                'label' => 'پرونده',
                'items' => [
                    'case_number' => 'شماره پرونده',
                    'title' => 'عنوان پرونده',
                    'status' => 'وضعیت فعلی',
                    'priority' => 'اولویت',
                    'currency' => 'ارز',
                    'incoterm' => 'ترم تحویل',
                    'exchange_rate' => 'نرخ تبدیل',
                    'description' => 'شرح پرونده',
                ],
            ],
            'customer' => [
                'label' => 'مشتری',
                'items' => [
                    'customer_name' => 'نام سازمان مشتری',
                    'customer_email' => 'ایمیل سازمان',
                    'customer_phone' => 'تلفن سازمان',
                    'customer_address' => 'آدرس سازمان',
                ],
            ],
            'expert' => [
                'label' => 'کارشناس',
                'items' => [
                    'expert_name' => 'نام کارشناس مسئول',
                    'expert_email' => 'ایمیل کارشناس',
                ],
            ],
            'document' => [
                'label' => 'سند',
                'items' => [
                    'document_number' => 'شماره سند',
                    'document_type' => 'نوع سند',
                    'net_amount' => 'مبلغ خالص',
                    'vat_percent' => 'درصد VAT',
                    'vat_amount' => 'مبلغ VAT',
                    'gross_amount' => 'مبلغ نهایی',
                    'document_currency' => 'ارز سند',
                    'document_incoterm' => 'Incoterm سند',
                    'document_date' => 'تاریخ سند',
                    'attachment_note' => 'پیوست',
                    'amount' => 'مبلغ ناخالص (amount)',
                    'email_signature' => 'امضای ایمیل/سند',
                ],
            ],
        ];
    }

    public static function flatKeys(): array
    {
        $keys = [];
        foreach (self::all() as $group) {
            foreach ($group['items'] as $k => $label) {
                $keys[$k] = $label;
            }
        }
        return $keys;
    }

    public static function varsFromCase($case, $document = null): array
    {
        $vars = [
            'company_name' => \App\Models\AppSetting::get('company_name', 'Company'),
            'company_address' => \App\Models\AppSetting::get('company_address', ''),
            'company_phone' => \App\Models\AppSetting::get('company_phone', ''),
            'company_email' => \App\Models\AppSetting::get('company_email', ''),
            'today' => now()->format('Y-m-d'),
            'now' => now()->format('Y-m-d H:i'),
            'document_date' => now()->format('Y/m/d'),
            'attachment_note' => '—',
            'email_signature' => \App\Models\AppSetting::get('email_signature', ''),
            'case_number' => $case?->case_number ?? '',
            'title' => $case?->title ?? '',
            'status' => $case?->current_status ?? '',
            'priority' => $case?->priority ?? '',
            'currency' => $case?->currency ?? 'EUR',
            'incoterm' => $case?->incoterm ?? '',
            'exchange_rate' => (string) ($case?->exchange_rate ?? ''),
            'description' => $case?->description ?? '',
            'customer_name' => $case?->customer?->name ?? '',
            'customer_email' => $case?->customer?->email ?? '',
            'customer_phone' => $case?->customer?->phone ?? '',
            'customer_address' => $case?->customer?->address ?? '',
            'expert_name' => $case?->expert?->name ?? '',
            'expert_email' => $case?->expert?->email ?? '',
        ];
        if ($document) {
            $vars['document_number'] = $document->document_number ?? '';
            $vars['document_type'] = $document->type ?? '';
            $vars['net_amount'] = number_format((float)($document->net_amount ?? 0), 2);
            $vars['vat_percent'] = (string) ($document->vat_percent ?? 0);
            $vars['vat_amount'] = number_format((float)($document->vat_amount ?? 0), 2);
            $vars['gross_amount'] = number_format((float)($document->gross_amount ?? 0), 2);
            $vars['document_currency'] = $document->currency ?? '';
            $vars['document_incoterm'] = $document->incoterm ?? '';
            $vars['document_date'] = optional($document->created_at)->format('Y/m/d') ?: now()->format('Y/m/d');
            $vars['amount'] = number_format((float)($document->gross_amount ?? 0), 2);
            $vars['attachment_note'] = $document->attachment_note ?? '—';
        }
        return $vars;
    }
}
