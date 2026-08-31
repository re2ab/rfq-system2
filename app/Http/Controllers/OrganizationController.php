<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Industry;
use App\Models\Tag;
use App\Services\CustomFieldService;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $query = Organization::withCount('contacts')->with('tags')->latest();

        if ($search = $request->get('q')) {
            $query->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('website', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        if ($tagId = $request->get('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        $organizations = $query->paginate(20);
        $tags = Tag::orderBy('name')->get();
        return view('organizations.index', compact('organizations', 'tags'));
    }

    public function create(CustomFieldService $cf)
    {
        $customFields = $cf->definitions('organization');
        $customValues = [];
        $customVisible = [];
        $tags = Tag::orderBy('name')->get();
        $industries = Industry::activeOptions();
        return view('organizations.create', compact('customFields', 'customValues', 'customVisible', 'tags', 'industries'));
    }

    public function store(Request $request, CustomFieldService $cf)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'type' => 'nullable|in:customer,supplier,both',
            'industry_id' => 'required|exists:industries,id',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);
        if (array_key_exists('website', $data)) { $data['website'] = $this->normalizeWebsite($data['website'] ?? null); }
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);

        $org = Organization::create($data);
        if ($tagIds) {
            $org->tags()->sync($tagIds);
        }
        $cf->save('organization', $org->id, $request->all());

        return redirect()->route('organizations.show', $org)->with('success', __('app.organization_created'));
    }

    public function show(Organization $organization, CustomFieldService $cf)
    {
        $organization->load(['contacts', 'tags']);
        $customFields = $cf->definitions('organization');
        $customValues = $cf->values('organization', $organization->id);
        $customVisible = $cf->visibility('organization', $organization->id);
        return view('organizations.show', compact('organization', 'customFields', 'customValues', 'customVisible'));
    }

    public function edit(Organization $organization, CustomFieldService $cf)
    {
        $organization->load('tags');
        $customFields = $cf->definitions('organization');
        $customValues = $cf->values('organization', $organization->id);
        $customVisible = $cf->visibility('organization', $organization->id);
        $tags = Tag::orderBy('name')->get();
        $industries = Industry::activeOptions();
        return view('organizations.edit', compact('organization', 'customFields', 'customValues', 'customVisible', 'tags', 'industries'));
    }

    public function update(Request $request, Organization $organization, CustomFieldService $cf)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'type' => 'nullable|in:customer,supplier,both',
            'industry_id' => 'required|exists:industries,id',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);
        if (array_key_exists('website', $data)) { $data['website'] = $this->normalizeWebsite($data['website'] ?? null); }
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['tag_ids']);
        $organization->update($data);
        $organization->tags()->sync($tagIds);
        $cf->save('organization', $organization->id, $request->all());
        return redirect()->route('organizations.show', $organization)->with('success', __('app.organization_updated'));
    }

    protected function normalizeWebsite(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') return null;
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        return $url;
    }


    public function destroy(Organization $organization)
    {
        // detach contacts optional keep contacts
        try {
            if (method_exists($organization, 'tags')) {
                $organization->tags()->detach();
            }
        } catch (\Throwable $e) {}
        $organization->delete();
        return redirect()->route('organizations.index')->with('success', 'سازمان حذف شد.');
    }

    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'bulk_action' => 'required|in:delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:organizations,id',
        ]);
        if ($data['bulk_action'] === 'delete') {
            $orgs = Organization::whereIn('id', $data['ids'])->get();
            foreach ($orgs as $org) {
                try { $org->tags()->detach(); } catch (\Throwable $e) {}
                $org->delete();
            }
        }
        return back()->with('success', 'سازمان‌های انتخاب‌شده حذف شدند.');
    }

}
