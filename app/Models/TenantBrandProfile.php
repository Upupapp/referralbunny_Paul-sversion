<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBrandProfile extends Model
{
    protected $fillable = [
        'tenant_id',
        'logo_url',
        'logo_path',
        'accent_color',
        'sidebar_color',
        'status',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Health score 0–100 based on how complete the brand configuration is.
     * Caller supplies tenant to check program/business name fields.
     */
    public static function computeHealthScore(Tenant $tenant, ?self $profile): int
    {
        $score = 0;

        if ($profile?->logo_url) {
            $score += 30;
        }
        if ($profile?->accent_color && $profile->accent_color !== '#FF5733') {
            $score += 25;
        }
        if ($profile?->sidebar_color && $profile->sidebar_color !== '#2D2B6E') {
            $score += 20;
        }
        if ($tenant->program_name) {
            $score += 15;
        }
        if ($tenant->business_name) {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * WCAG AA contrast ratio check: accent on white background.
     * Returns true if contrast ratio >= 4.5:1.
     */
    public static function passesWcagAa(string $hexColor): bool
    {
        $hex = ltrim($hexColor, '#');
        if (strlen($hex) !== 6) {
            return true;
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $toLinear = fn(float $c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $L = 0.2126 * $toLinear($r) + 0.7152 * $toLinear($g) + 0.0722 * $toLinear($b);

        // White luminance = 1.0; ratio = (1 + 0.05) / (L + 0.05)
        $ratio = 1.05 / ($L + 0.05);

        return $ratio >= 4.5;
    }
}
