<?php
namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::orderBy('entity')->orderBy('name')->get()->groupBy('entity');
        $entities = Tag::ENTITIES;
        return view('settings.tags', compact('tags', 'entities'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:20',
            'entity' => 'required|in:case,contact,organization,task',
        ]);
        Tag::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.substr(uniqid(), -4),
            'color' => $data['color'] ?? '#0f766e',
            'entity' => $data['entity'],
        ]);
        return back()->with('success', 'تگ ایجاد شد.');
    }

    public function update(Request $request, Tag $tag)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:20',
            'entity' => 'required|in:case,contact,organization,task',
        ]);
        $tag->update([
            'name' => $data['name'],
            'color' => $data['color'] ?? $tag->color,
            'entity' => $data['entity'],
        ]);
        return back()->with('success', 'تگ به‌روز شد.');
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return back()->with('success', 'تگ حذف شد.');
    }
}
