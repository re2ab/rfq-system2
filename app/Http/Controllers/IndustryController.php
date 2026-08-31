<?php
namespace App\Http\Controllers;

use App\Models\Industry;
use Illuminate\Http\Request;

class IndustryController extends Controller
{
    public function index()
    {
        $industries = Industry::orderBy('sort_order')->orderBy('name')->get();
        return view('settings.industries', compact('industries'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:40|unique:industries,code',
            'sort_order' => 'nullable|integer|min:0|max:9999',
        ]);
        Industry::create([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active' => true,
        ]);
        return back()->with('success', 'صنعت اضافه شد.');
    }

    public function update(Request $request, Industry $industry)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'code' => 'nullable|string|max:40|unique:industries,code,'.$industry->id,
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);
        $industry->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? $industry->code,
            'sort_order' => $data['sort_order'] ?? $industry->sort_order,
            'is_active' => $request->boolean('is_active', $industry->is_active),
        ]);
        return back()->with('success', 'صنعت به‌روزرسانی شد.');
    }

    public function destroy(Industry $industry)
    {
        if ($industry->organizations()->exists()) {
            return back()->withErrors(['industry' => 'این صنعت به سازمان‌هایی وصل است؛ ابتدا آن‌ها را تغییر دهید یا صنعت را غیرفعال کنید.']);
        }
        $industry->delete();
        return back()->with('success', 'حذف شد.');
    }
}
