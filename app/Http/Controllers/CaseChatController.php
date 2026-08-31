<?php
namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Support\ModuleGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CaseChatController extends Controller
{
    public function store(Request $request, CaseModel $case)
    {
        if (!ModuleGate::enabled('case_chat')) {
            abort(403, 'پیام‌رسان داخلی غیرفعال است');
        }
        $data = $request->validate(['body' => 'required|string|max:5000']);
        if (!Schema::hasTable('case_chat_messages')) {
            return back()->withErrors(['body' => 'جدول گفتگو آماده نیست — migrate را اجرا کنید']);
        }
        $id = DB::table('case_chat_messages')->insertGetId([
            'case_id' => $case->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // mention notify @email or @name simple
        if (preg_match_all('/@([\w\.\-]+)/u', $data['body'], $m)) {
            $ns = app(NotificationService::class);
            foreach (array_unique($m[1]) as $token) {
                $user = \App\Models\User::where('email', 'like', $token.'%')
                    ->orWhere('name', 'like', '%'.$token.'%')
                    ->first();
                if ($user && $user->id !== auth()->id()) {
                    $ns->notify($user, 'منشن در گفتگوی پرونده', $case->case_number ?? ('#'.$case->id), url('/cases/'.$case->id));
                }
            }
        }
        AuditLogger::log('case_chat', 'case', $case->id, ['message_id' => $id]);
        return back()->with('success', 'پیام ثبت شد');
    }
}
