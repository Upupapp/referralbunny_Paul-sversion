<?php

namespace App\Services;

/**
 * Centralised display-name resolution for all user types.
 *
 * Priority chain:
 *  1. Anonymity override   → "Anonymous Referrer"
 *  2. Partner access check → null (caller should not render)
 *  3. Nickname             → use it
 *  4. Full name            → first + last
 *  5. Name (legacy field)  → use it
 *  6. Email prefix         → safe masked label
 *  7. Fallback             → "Referral Bunny User"
 */
class UserDisplayNameService
{
    /**
     * Resolve the display name for any user object.
     *
     * @param  object       $user          Any user model (User, TenantUser, Reseller, Partner)
     * @param  bool         $anonymous     True if Referrer anonymity applies and viewer is not authorised
     * @param  string|null  $userType      'super_admin' | 'tenant_admin' | 'referrer' | 'partner'
     * @return string
     */
    public static function resolve(object $user, bool $anonymous = false, ?string $userType = null): string
    {
        // 1. Anonymity override
        if ($anonymous) {
            return 'Anonymous Referrer';
        }

        // 2. Nickname
        if (!empty($user->nickname)) {
            return trim($user->nickname);
        }

        // 3. first_name + last_name
        $first = $user->first_name ?? null;
        $last  = $user->last_name  ?? null;
        if ($first || $last) {
            return trim("{$first} {$last}");
        }

        // 4. Legacy 'name' field (Reseller, User)
        if (!empty($user->name)) {
            return trim($user->name);
        }

        // 5. Safe email prefix
        if (!empty($user->email)) {
            $parts = explode('@', $user->email);
            return $parts[0] ?? 'user';
        }

        return 'Referral Bunny User';
    }

    /**
     * Return initials (max 2 chars) for avatar fallback.
     */
    public static function initials(object $user, bool $anonymous = false): string
    {
        if ($anonymous) {
            return '?';
        }

        $name = self::resolve($user, false);
        $words = preg_split('/\s+/', trim($name));

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
        }

        return strtoupper(substr($name, 0, 2));
    }

    /**
     * Return the URL to the user's profile photo, or null if not set.
     */
    public static function photoUrl(object $user, bool $anonymous = false): ?string
    {
        if ($anonymous) {
            return null; // Caller should render generic avatar
        }

        if (empty($user->profile_photo_path)) {
            return null;
        }

        return asset('storage/' . $user->profile_photo_path);
    }

    /**
     * Common timezone list for select dropdowns.
     */
    public static function timezones(): array
    {
        return [
            'Asia/Manila', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'Asia/Jakarta',
            'Asia/Bangkok', 'Asia/Hong_Kong', 'Asia/Tokyo', 'Asia/Seoul',
            'Asia/Dubai', 'Asia/Kolkata', 'Asia/Dhaka',
            'Australia/Sydney', 'Australia/Melbourne', 'Australia/Perth',
            'Pacific/Auckland', 'Pacific/Fiji',
            'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Amsterdam',
            'America/New_York', 'America/Chicago', 'America/Denver',
            'America/Los_Angeles', 'America/Toronto', 'America/Vancouver',
            'America/Sao_Paulo', 'America/Mexico_City',
            'UTC',
        ];
    }

    /**
     * Calculate a simple profile completion percentage.
     */
    public static function completionPercent(object $user): int
    {
        $fields = ['nickname', 'phone_number', 'timezone', 'bio', 'profile_photo_path'];
        $hasName = !empty($user->first_name) || !empty($user->last_name) || !empty($user->name);
        $score   = $hasName ? 1 : 0;

        foreach ($fields as $field) {
            if (!empty($user->$field)) {
                $score++;
            }
        }

        return (int) round(($score / (count($fields) + 1)) * 100);
    }
}
