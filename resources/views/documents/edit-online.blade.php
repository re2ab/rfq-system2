<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $revision->document?->documentType?->name_fa ?? 'سند' }} {{ $revision->formatted_number ?: '(پیش‌نویس)' }} — ویرایش آنلاین</title>
{{--
  M11 — عمداً این صفحه از layouts.app گسترش پیدا نمی‌کند (بدون رِیل/هدر/سایدبار):
  ویجت ONLYOFFICE خودش یک محیط کامل شبیه Word/Excel با نوار ابزار و منوی خودش
  است و به تمام فضای صفحه نیاز دارد — دقیقاً مثل Google Docs/Zoho Writer که در
  یک تب/صفحه‌ی مستقل و تمام‌صفحه باز می‌شوند، نه داخل چارچوب برنامه.
--}}
<style>
  html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; font-family: Tahoma, sans-serif; }
  #topbar {
    display: flex; align-items: center; justify-content: space-between;
    height: 48px; padding: 0 14px; background: #16191f; color: #fff; box-sizing: border-box;
    font-size: 13px;
  }
  #topbar a {
    color: #fff; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 10px; border-radius: 6px; background: rgba(255,255,255,.08);
  }
  #topbar a:hover { background: rgba(255,255,255,.16); }
  #topbar .title { opacity: .85; }
  #editor-wrap { position: absolute; inset: 48px 0 0 0; }
  #editor-placeholder { width: 100%; height: 100%; }
  #editor-error {
    display: none; padding: 24px; max-width: 520px; margin: 40px auto; text-align: center;
    font-size: 14px; line-height: 1.8; color: #444;
  }
</style>
</head>
<body>

<div id="topbar">
  <a href="{{ route('documents.show', $revision->document_id) }}">&larr; بازگشت به سند</a>
  <span class="title">{{ $revision->document?->documentType?->name_fa ?? 'سند' }} — نسخه {{ $revision->revision_number }}</span>
</div>

<div id="editor-wrap">
  <div id="editor-placeholder"></div>
  <div id="editor-error">
    بارگذاری ویرایشگر آنلاین ممکن نشد. اتصال به سرویس ONLYOFFICE را بررسی کنید یا
    از همان دکمه‌ی «دانلود / آپلود مجدد» در صفحه‌ی سند استفاده کنید.
  </div>
</div>

<script src="{{ rtrim($dsUrl, '/') }}/web-apps/apps/api/documents/api.js" onerror="document.getElementById('editor-error').style.display='block'"></script>
<script>
  (function () {
    if (typeof DocsAPI === 'undefined') {
      document.getElementById('editor-error').style.display = 'block';
      return;
    }
    var config = @json($config);
    new DocsAPI.DocEditor('editor-placeholder', config);
  })();
</script>

</body>
</html>
