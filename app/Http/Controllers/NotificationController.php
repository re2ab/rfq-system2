<?php
namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $items = AppNotification::where('user_id', Auth::id())->latest()->paginate(30);
        return view('notifications.index', compact('items'));
    }

    public function markRead(AppNotification $notification)
    {
        if ($notification->user_id !== Auth::id()) abort(403);
        $notification->update(['is_read' => true]);
        return back();
    }
}
