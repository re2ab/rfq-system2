<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplateProposalSeeder extends Seeder
{
    public function run(): void
    {
        $header = <<<'HTML'
<div style="direction:rtl;font-family:Tahoma,Vazirmatn,Arial,sans-serif;font-size:12px;color:#1e293b;border-bottom:2px solid #b8703c;padding-bottom:12px;margin-bottom:16px;">
  <table style="width:100%;border-collapse:collapse;">
    <tr>
      <td style="width:55%;vertical-align:top;text-align:right;">
        <div style="font-size:16px;font-weight:bold;color:#b8703c;">{{company_name}}</div>
        <div style="margin-top:4px;color:#64748b;">شرکت صنعتی و بازرگانی</div>
      </td>
      <td style="width:45%;vertical-align:top;text-align:left;font-size:11px;color:#475569;">
        <div>تاریخ: <strong>{{document_date}}</strong></div>
        <div>شماره: <strong>{{document_number}}</strong></div>
        <div>پیوست: <strong>{{attachment_note}}</strong></div>
      </td>
    </tr>
  </table>
</div>
HTML;

        $footer = <<<'HTML'
<div style="direction:rtl;font-family:Tahoma,Vazirmatn,Arial,sans-serif;font-size:11px;color:#64748b;border-top:1px solid #e2e8f0;padding-top:12px;margin-top:24px;">
  <p style="margin:0 0 8px;">با احترام</p>
  <p style="margin:0;"><strong>{{company_name}}</strong></p>
  <p style="margin:4px 0 0;color:#94a3b8;">{{email_signature}}</p>
</div>
HTML;

        $techBody = <<<'HTML'
<div style="direction:rtl;font-family:Tahoma,Vazirmatn,Arial,sans-serif;font-size:13px;line-height:1.8;color:#1e293b;">
  <p style="text-align:center;font-size:15px;font-weight:bold;margin:0 0 16px;">{{customer_name}}</p>
  <p style="margin:0 0 12px;"><strong>موضوع:</strong> ارائه پیشنهاد فنی مربوط به درخواست خرید شماره <strong>{{case_number}}</strong></p>
  <p style="margin:0 0 16px;">با سلام<br>
  احتراماً به پیوست، پیشنهاد فنی این شرکت برای اقلام درخواستی به حضورتان ارسال می‌گردد. خواهشمند است این شرکت را از نتیجه بررسی خود مطلع فرمایید.</p>

  <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:12px;">
    <thead>
      <tr style="background:#f1f5f9;">
        <th style="border:1px solid #94a3b8;padding:8px;width:8%;">آیتم</th>
        <th style="border:1px solid #94a3b8;padding:8px;">مشخصات</th>
        <th style="border:1px solid #94a3b8;padding:8px;width:12%;">تعداد</th>
        <th style="border:1px solid #94a3b8;padding:8px;width:18%;">توضیحات</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">1</td>
        <td style="border:1px solid #94a3b8;padding:8px;">[نام کالا / مدل / Origin]</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">— عدد</td>
        <td style="border:1px solid #94a3b8;padding:8px;"></td>
      </tr>
      <tr>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">2</td>
        <td style="border:1px solid #94a3b8;padding:8px;">[نام کالا / مدل / Origin]</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">— عدد</td>
        <td style="border:1px solid #94a3b8;padding:8px;">پیوست دارد</td>
      </tr>
    </tbody>
  </table>

  <ul style="margin:16px 0;padding-right:20px;">
    <li>ترم پرداخت: ۲۵ درصد پیش‌پرداخت و ۷۵ درصد باقیمانده پس از تحویل کالا.</li>
    <li>زمان تحویل: ۷–۸ هفته پس از دریافت پیش‌پرداخت و تأیید پیشنهاد.</li>
    <li>ترم تحویل کالا: <strong>{{incoterm}}</strong></li>
    <li>شرکت سازنده: [نام سازنده]</li>
    <li>گواهینامه: Certificate of Origin به همراه محموله ارائه خواهد شد.</li>
  </ul>
</div>
HTML;

        $finBody = <<<'HTML'
<div style="direction:rtl;font-family:Tahoma,Vazirmatn,Arial,sans-serif;font-size:13px;line-height:1.8;color:#1e293b;">
  <p style="text-align:center;font-size:15px;font-weight:bold;margin:0 0 16px;">{{customer_name}}</p>
  <p style="margin:0 0 12px;"><strong>موضوع:</strong> ارائه پیشنهاد مالی و بازرگانی مربوط به درخواست خرید — {{case_number}}</p>
  <p style="margin:0 0 16px;">با سلام؛<br>
  احتراماً بدینوسیله پیشنهاد مالی و بازرگانی این شرکت جهت اقلام درخواستی درخواست خرید به شرح ذیل به حضورتان ارائه می‌گردد:</p>

  <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:12px;">
    <thead>
      <tr style="background:#f1f5f9;">
        <th style="border:1px solid #94a3b8;padding:8px;width:7%;">آیتم</th>
        <th style="border:1px solid #94a3b8;padding:8px;">مشخصات</th>
        <th style="border:1px solid #94a3b8;padding:8px;width:10%;">تعداد</th>
        <th style="border:1px solid #94a3b8;padding:8px;width:14%;">قیمت واحد ({{currency}})</th>
        <th style="border:1px solid #94a3b8;padding:8px;width:14%;">قیمت کل ({{currency}})</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">1</td>
        <td style="border:1px solid #94a3b8;padding:8px;">[Brand / Type / Model]</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">—</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:left;direction:ltr;">0.00</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:left;direction:ltr;">0.00</td>
      </tr>
      <tr>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">2</td>
        <td style="border:1px solid #94a3b8;padding:8px;">[Brand / Type / Model]</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:center;">—</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:left;direction:ltr;">0.00</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:left;direction:ltr;">0.00</td>
      </tr>
      <tr style="background:#f8fafc;font-weight:bold;">
        <td colspan="4" style="border:1px solid #94a3b8;padding:8px;text-align:left;">جمع کل ({{currency}}):</td>
        <td style="border:1px solid #94a3b8;padding:8px;text-align:left;direction:ltr;">{{amount}}</td>
      </tr>
    </tbody>
  </table>

  <ul style="margin:16px 0;padding-right:20px;">
    <li>مدت اعتبار پیشنهاد: ۱۵ روز پس از تاریخ صدور.</li>
    <li>ترم پرداخت: ۲۵ درصد به‌صورت پیش‌پرداخت، ۷۵ درصد باقیمانده در مقابل تحویل کالا و اسناد حمل.</li>
    <li>زمان تحویل: طبق توافق پس از دریافت پیش‌پرداخت و تأیید پیشنهاد.</li>
    <li>ترم تحویل کالا: <strong>{{incoterm}}</strong></li>
    <li>شرکت سازنده: [نام سازنده]</li>
  </ul>
</div>
HTML;

        $now = now();

        // technical default
        $existsTech = DB::table('templates')
            ->where('type', 'technical_proposal')
            ->where('code', 'TC-DEFAULT')
            ->first();

        $techData = [
            'type' => 'technical_proposal',
            'name' => 'پیشنهاد فنی استاندارد (TC)',
            'code' => 'TC-DEFAULT',
            'header' => $header,
            'body' => $techBody,
            'footer' => $footer,
            'account_type' => null,
            'is_default' => true,
            'version' => 1,
            'updated_at' => $now,
        ];

        if ($existsTech) {
            DB::table('templates')->where('id', $existsTech->id)->update($techData);
        } else {
            DB::table('templates')->insert(array_merge($techData, ['created_at' => $now]));
        }

        // financial default
        $existsFin = DB::table('templates')
            ->where('type', 'financial_proposal')
            ->where('code', 'FI-DEFAULT')
            ->first();

        $finData = [
            'type' => 'financial_proposal',
            'name' => 'پیشنهاد مالی استاندارد (FI)',
            'code' => 'FI-DEFAULT',
            'header' => $header,
            'body' => $finBody,
            'footer' => $footer,
            'account_type' => null,
            'is_default' => true,
            'version' => 1,
            'updated_at' => $now,
        ];

        if ($existsFin) {
            DB::table('templates')->where('id', $existsFin->id)->update($finData);
        } else {
            DB::table('templates')->insert(array_merge($finData, ['created_at' => $now]));
        }
    }
}
