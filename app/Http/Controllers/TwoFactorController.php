<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        return view('twofactor.show', [
            'enabled' => !empty($user->two_factor_secret),
            'secret' => $user->two_factor_secret,
        ]);
    }

    public function enable(Request $request)
    {
        $user = Auth::user();
        // Simple app-level secret (for production use Google2FA package)
        $secret = strtoupper(Str::random(16));
        $user->forceFill(['two_factor_secret' => $secret])->save();
        return back()->with('success', '2FA فعال شد. کد مخفی را در اپ Authenticator ذخیره کنید (نسخه ساده).');
    }

    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|current_password']);
        Auth::user()->forceFill(['two_factor_secret' => null])->save();
        return back()->with('success', '2FA غیرفعال شد.');
    }
}
