<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><title>{{ $document->document_number }}</title>
<style>body{font-family:Tahoma,sans-serif;padding:32px;line-height:1.7}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:8px;text-align:right}
@media print{.no-print{display:none}}</style><link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"/>
<style>body{font-family:Vazirmatn,Tahoma,sans-serif}</style>
</head>
<body>
<button class="no-print" onclick="window.print()">چاپ / ذخیره PDF</button>
<h1>{{ $document->document_number }}</h1>
<p class="no-print" style="color:#b91c1c">این فقط خلاصه‌ی اطلاعات سند است، نه فایل واقعی Word/Excel — چون سرویس تبدیل PDF روی این سرور فعال نیست. فایل واقعی را از دکمه‌ی «دانلود» در صفحه‌ی سند بگیرید.</p>
<p>نوع: {{ $document->documentType->name_fa ?? $document->type }} | پرونده: {{ $document->case?->case_number }}</p>
@if($document->typeSupportsLines())
<p>ترم تحویل: {{ $document->incoterm }} | ارز: {{ currency_label($document->currency) }}</p>
<table>
<tr><th>خالص</th><td>{{ number_format($document->net_amount,2) }}</td></tr>
<tr><th>VAT ({{ $document->vat_percent }}%)</th><td>{{ number_format($document->vat_amount,2) }}</td></tr>
<tr><th>جمع</th><td>{{ number_format($document->gross_amount,2) }}</td></tr>
</table>
@endif
@foreach($document->revisions as $rev)
  @if($rev->content)
<hr><pre style="white-space:pre-wrap">{{ $rev->content }}</pre>
  @endif
@endforeach
</body></html>
