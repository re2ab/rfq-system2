{{-- ادیتور حرفه‌ای شبیه ورد (TinyMCE) --}}
<script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js" referrerpolicy="origin"></script>
<script>
window.initRfqEditor = function(selector, options) {
  options = options || {};
  tinymce.init(Object.assign({
    selector: selector,
    directionality: 'rtl',
    language: undefined,
    height: options.height || 480,
    menubar: 'file edit view insert format tools table',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount directionality',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignright aligncenter alignleft alignjustify | bullist numlist outdent indent | table link image | removeformat code fullscreen | ltr rtl',
    content_style: 'body { font-family: Vazirmatn, Tahoma, Arial, sans-serif; font-size: 14px; }',
    branding: false,
    promotion: false,
    convert_urls: false,
    setup: function(editor) {
      editor.on('change keyup', function() {
        editor.save();
      });
      if (options.onInit) editor.on('init', options.onInit);
    }
  }, options.extra || {}));
};
</script>
