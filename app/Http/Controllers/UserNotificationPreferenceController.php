<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationPreferenceController extends Controller
{
    private function assertOwnership(UserNotificationPreference $pref): void
    {
        abort_unless(Auth::guard('web')->check(), 403, 'Super admin access required.');
        abort_unless(
            (string) $pref->user_id === (string) Auth::guard('web')->id(),
            403,
            'You do not have access to this preference record.'
        );
    }

    public function index()
    {
        abort_unless(Auth::guard('web')->check(), 403);
        $prefs = UserNotificationPreference::where('user_id', Auth::guard('web')->id())->get();
        return response()->json($prefs);
    }

    public function store(Request $request)
    {
        abort_unless(Auth::guard('web')->check(), 403);
        $validated = $request->validate([
            'notification_type'      => ['required', 'string', 'max:100'],
            'in_app_enabled'         => ['sometimes', 'boolean'],
            'email_enabled'          => ['sometimes', 'boolean'],
            'sms_enabled'            => ['sometimes', 'boolean'],
            'daily_digest_enabled'   => ['sometimes', 'boolean'],
            'weekly_digest_enabled'  => ['sometimes', 'boolean'],
            'monthly_report_enabled' => ['sometimes', 'boolean'],
            'critical_alerts_only'   => ['sometimes', 'boolean'],
            'categories_json'        => ['sometimes', 'array'],
        ]);
        $pref = UserNotificationPreference::create(
            array_merge($validated, ['user_id' => Auth::guard('web')->id()])
        );
        return response()->json($pref, 201);
    }

    public function show(UserNotificationPreference $userNotificationPreference)
    {
        $this->assertOwnership($userNotificationPreference);
        return response()->json($userNotificationPreference);
    }

    public function update(Request $request, UserNotificationPreference $userNotificationPreference)
    {
        $this->assertOwnership($userNotificationPreference);
        $validated = $request->validate([
            'in_app_enabled'         => ['sometimes', 'boolean'],
            'email_enabled'          => ['sometimes', 'boolean'],
            'sms_enabled'            => ['sometimes', 'boolean'],
            'daily_digest_enabled'   => ['sometimes', 'boolean'],
            'weekly_digest_enabled'  => ['sometimes', 'boolean'],
            'monthly_report_enabled' => ['sometimes', 'boolean'],
            'critical_alerts_only'   => ['sometimes', 'boolean'],
            'categories_json'        => ['sometimes', 'array'],
        ]);
        $userNotificationPreference->update($validated);
        return response()->json($userNotificationPreference->fresh());
    }

    public function destroy(UserNotificationPreference $userNotificationPreference)
    {
        $this->assertOwnership($userNotificationPreference);
        $userNotificationPreference->delete();
        return response()->json(['deleted' => true]);
    }
}
