@props(['action', 'formId'])
<select name="bulk_action" class="rfq-f-select" size="1" form="{{ $formId }}" style="flex-shrink:0">
  <option value="">عملیات گروهی…</option>
  <option value="delete">حذف انتخاب شده‌ها</option>
</select>
<button type="submit" form="{{ $formId }}" formmethod="POST" formaction="{{ $action }}" formnovalidate
        class="btn btn-danger-soft btn-sm rfq-f-btn bulk-apply-btn" data-form-id="{{ $formId }}"
        style="flex-shrink:0">اعمال</button>
