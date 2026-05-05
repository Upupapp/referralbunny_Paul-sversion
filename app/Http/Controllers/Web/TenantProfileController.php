<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantProfileController extends Controller
{
    public function show(string $tenantId)
    {
        $tenant = Tenant::findOrFail($tenantId);
        $user   = Auth::guard('tenant')->user() ?? Auth::guard('web')->user();
        return view('tenant.profile.index', compact('tenant', 'user'));
    }

    public function update(Request $request, string $tenantId)
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) abort(403);

        $data = $request->validate([
            'first_name'   => 'nullable|string|max:100',
            'last_name'    => 'nullable|string|max:100',
            'nickname'     => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:30',
            'job_title'    => 'nullable|string|max:100',
            'department'   => 'nullable|string|max:100',
            'organization' => 'nullable|string|max:150',
            'location'     => 'nullable|string|max:150',
            'timezone'     => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:20',
            'bio'          => 'nullable|string|max:500',
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request, string $tenantId)
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) abort(403);

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Delete old photo
        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $request->file('photo')->storeAs(
            "profile-photos/tenant/{$user->id}",
            Str::random(32) . '.' . $request->file('photo')->extension(),
            'public'
        );

        $user->update(['profile_photo_path' => $path]);

        return back()->with('success', 'Profile photo updated.');
    }

    public function destroyPhoto(string $tenantId)
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) abort(403);

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
        }

        return back()->with('success', 'Profile photo removed.');
    }
}
