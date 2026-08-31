<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'locale' => 'nullable|in:fa,en',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->boolean('remove_avatar') && !$request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = null;
        } elseif ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);
        if (!empty($data['locale'])) {
            session(['locale' => $data['locale']]);
        }

        return back()->with('success', 'پروفایل به‌روز شد.');
    }
}
