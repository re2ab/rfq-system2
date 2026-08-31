<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Document;
use App\Models\Task;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $results = [
            'cases' => collect(),
            'contacts' => collect(),
            'organizations' => collect(),
            'documents' => collect(),
            'tasks' => collect(),
        ];

        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $results['cases'] = CaseModel::where(function ($w) use ($like) {
                $w->where('case_number', 'like', $like)
                  ->orWhere('title', 'like', $like)
                  ->orWhere('description', 'like', $like);
            })->limit(20)->get();

            $results['contacts'] = Contact::where(function ($w) use ($like) {
                $w->where('first_name', 'like', $like)
                  ->orWhere('last_name', 'like', $like)
                  ->orWhere('email', 'like', $like)
                  ->orWhere('mobile', 'like', $like);
            })->limit(20)->get();

            $results['organizations'] = Organization::where('name', 'like', $like)
                ->orWhere('national_id', 'like', $like)->limit(20)->get();

            $results['documents'] = Document::where('document_number', 'like', $like)
                ->orWhere('title', 'like', $like)->limit(20)->get();

            $results['tasks'] = Task::where('title', 'like', $like)
                ->orWhere('description', 'like', $like)->limit(20)->get();
        }

        return view('search.index', compact('q', 'results'));
    }
}
