<?php
namespace App\Http\Controllers;

use App\Models\CustomFieldDefinition;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    public function index()
    {
        $fields = CustomFieldDefinition::orderBy('entity')->orderBy('sort_order')->orderBy('id')->get()->groupBy('entity');
        $entities = [
            'case' => 'پرونده‌ها',
            'contact' => 'مخاطبان',
            'organization' => 'سازمان‌ها',
            'task' => 'وظایف',
        ];
        return view('settings.custom_fields', compact('fields', 'entities'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'entity' => 'required|in:case,contact,organization,task',
            'key' => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'label' => 'required|string|max:100',
            'field_type' => 'required|in:text,number,alphanumeric,date,select',
            'options' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);
        $exists = CustomFieldDefinition::where('entity', $data['entity'])->where('key', $data['key'])->exists();
        if ($exists) {
            return back()->withErrors(['key' => 'این کلید برای این بخش قبلاً تعریف شده.']);
        }
        $data['options'] = $data['field_type'] === 'select' && !empty($data['options'])
            ? array_values(array_filter(array_map('trim', explode(',', $data['options']))))
            : null;
        $data['is_required'] = $request->boolean('is_required');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        CustomFieldDefinition::create($data);
        return back()->with('success', 'فیلد سفارشی اضافه شد.');
    }

    public function update(Request $request, CustomFieldDefinition $customField)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'field_type' => 'required|in:text,number,alphanumeric,date,select',
            'options' => 'nullable|string',
            'is_required' => 'boolean',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);
        $data['options'] = $data['field_type'] === 'select' && !empty($data['options'])
            ? array_values(array_filter(array_map('trim', explode(',', $data['options']))))
            : null;
        $data['is_required'] = $request->boolean('is_required');
        $customField->update($data);
        return back()->with('success', 'فیلد به‌روز شد.');
    }

    public function destroy(CustomFieldDefinition $customField)
    {
        $customField->delete();
        return back()->with('success', 'حذف شد.');
    }
}
