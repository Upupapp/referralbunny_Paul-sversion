<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PlatformProfileController extends Controller
{
    public function show()
    {
        $user = Auth::guard('web')->user();
        return view('platform.profile', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::guard('web')->user();

        $data = $request->validate([
            'name'         => 'nullable|string|max:100',
            'nickname'     => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:30',
            'job_title'    => 'nullable|string|max:100',
            'department'   => 'nullable|string|max:100',
            'location'     => 'nullable|string|max:150',
            'timezone'     => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:20',
            'bio'          => 'nullable|string|max:500',
        ]);

        $user->update($data);
        Auth::guard('web')->setUser($user->fresh());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request)
    {
        $user = Auth::guard('web')->user();

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $request->file('photo')->storeAs(
            "profile-photos/admin/{$user->id}",
            Str::random(32) . '.' . $request->file('photo')->extension(),
            'public'
        );

        $user->update(['profile_photo_path' => $path]);
        Auth::guard('web')->setUser($user->fresh());

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto()
    {
        $user = Auth::guard('web')->user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
            Auth::guard('web')->setUser($user->fresh());
        }

        return back()->with('success', 'Profile photo removed.');
    }
}
