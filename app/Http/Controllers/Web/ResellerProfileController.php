<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResellerProfileController extends Controller
{
    private function reseller()
    {
        return Auth::guard('reseller')->user()
            ?? Auth::guard('web')->user(); // allow super admin to preview
    }

    public function show(string $tenantId)
    {
        $tenant   = Tenant::findOrFail($tenantId);
        $reseller = $this->reseller();
        return view('reseller.profile', compact('tenant', 'reseller'));
    }

    public function update(Request $request, string $tenantId)
    {
        $reseller = Auth::guard('reseller')->user();
        if (!$reseller) abort(403);

        $data = $request->validate([
            'nickname'     => 'nullable|string|max:50',
            'phone'        => 'nullable|string|max:30',
            'job_title'    => 'nullable|string|max:100',
            'department'   => 'nullable|string|max:100',
            'organization' => 'nullable|string|max:150',
            'territory'    => 'nullable|string|max:150',
            'location'     => 'nullable|string|max:150',
            'timezone'     => 'nullable|string|max:100',
            'language'     => 'nullable|string|max:20',
            'bio'          => 'nullable|string|max:500',
        ]);

        $reseller->update($data);
        Auth::guard('reseller')->setUser($reseller->fresh());

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePhoto(Request $request, string $tenantId)
    {
        $reseller = Auth::guard('reseller')->user();
        if (!$reseller) abort(403);

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($reseller->profile_photo_path) {
            Storage::disk('public')->delete($reseller->profile_photo_path);
        }

        $path = $request->file('photo')->storeAs(
            "profile-photos/reseller/{$reseller->id}",
            Str::random(32) . '.' . $request->file('photo')->extension(),
            'public'
        );

        $reseller->update(['profile_photo_path' => $path]);
        Auth::guard('reseller')->setUser($reseller->fresh());

        return back()->with('success', 'Profile photo updated.');
    }

    public function toggleAnonymous(Request $request, string $tenantId)
    {
        $reseller = Auth::guard('reseller')->user();
        if (!$reseller) abort(403);

        $isAnon  = (bool) $request->input('is_anonymous');
        $updates = ['is_anonymous' => $isAnon];

        // Mark anonymous-mode onboarding as completed on first ever toggle,
        // regardless of direction. This stops the recurring guide from showing.
        if (!$reseller->anonymous_onboarded_at) {
            $updates['anonymous_onboarded_at'] = now();
        }

        $reseller->update($updates);
        Auth::guard('reseller')->setUser($reseller->fresh());

        $msg = $isAnon
            ? 'Anonymous mode enabled. Your name and photo are now hidden from other referrers.'
            : 'Anonymous mode disabled. Your profile is now visible to other referrers.';

        return back()->with('success', $msg);
    }

    public function destroyPhoto(string $tenantId)
    {
        $reseller = Auth::guard('reseller')->user();
        if (!$reseller) abort(403);

        if ($reseller->profile_photo_path) {
            Storage::disk('public')->delete($reseller->profile_photo_path);
            $reseller->update(['profile_photo_path' => null]);
            Auth::guard('reseller')->setUser($reseller->fresh());
        }

        return back()->with('success', 'Profile photo removed.');
    }
}
