<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\UserDisplayNameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PartnerProfileController extends Controller
{
    private function partner()
    {
        return Auth::guard('partner')->user();
    }

    public function show()
    {
        $partner   = $this->partner();
        $timezones = UserDisplayNameService::timezones();
        $photoUrl  = UserDisplayNameService::photoUrl($partner);
        $initials  = UserDisplayNameService::initials($partner);
        $completion = UserDisplayNameService::completionPercent($partner);

        return view('partner.profile', compact('partner', 'timezones', 'photoUrl', 'initials', 'completion'));
    }

    public function update(Request $request)
    {
        $partner = Auth::guard('partner')->user();
        if (!$partner) abort(403);

        $data = $request->validate([
            'first_name'   => 'nullable|string|max:100',
            'last_name'    => 'nullable|string|max:100',
            'nickname'     => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:30',
            'organization' => 'nullable|string|max:150',
            'location'     => 'nullable|string|max:150',
            'timezone'     => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:20',
            'bio'          => 'nullable|string|max:500',
        ]);

        $partner->update($data);
        Auth::guard('partner')->setUser($partner->fresh());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request)
    {
        $partner = Auth::guard('partner')->user();
        if (!$partner) abort(403);

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($partner->profile_photo_path) {
            Storage::disk('public')->delete($partner->profile_photo_path);
        }

        $path = $request->file('photo')->storeAs(
            "profile-photos/partner/{$partner->id}",
            Str::random(32) . '.' . $request->file('photo')->extension(),
            'public'
        );

        $partner->update(['profile_photo_path' => $path]);
        Auth::guard('partner')->setUser($partner->fresh());

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto()
    {
        $partner = Auth::guard('partner')->user();
        if (!$partner) abort(403);

        if ($partner->profile_photo_path) {
            Storage::disk('public')->delete($partner->profile_photo_path);
            $partner->update(['profile_photo_path' => null]);
            Auth::guard('partner')->setUser($partner->fresh());
        }

        return back()->with('success', 'Profile photo removed.');
    }

    public function changePassword(Request $request, string $tenantId): \Illuminate\Http\RedirectResponse
    {
        $partner = Auth::guard('partner')->user();
        if (!$partner) abort(403);

        $request->validate([
            'current_password'          => 'required|string',
            'new_password'              => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ]);

        if (!Hash::check($request->current_password, $partner->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }

        $partner->update(['password' => Hash::make($request->new_password)]);

        $request->session()->regenerate();
        Auth::guard('partner')->setUser($partner->fresh());

        return back()->with('password_success', 'Password changed successfully.');
    }
}
