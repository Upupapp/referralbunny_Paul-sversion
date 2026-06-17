<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantBrandProfile;
use App\Models\TenantBrandVersion;
use App\Models\TenantMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantBrandingController extends Controller
{
    private const ALLOWED_LOGO_MIMES  = ['image/jpeg', 'image/png', 'image/webp'];
    private const ALLOWED_EXTENSIONS  = ['jpg', 'jpeg', 'png', 'webp'];
    private const MAX_LOGO_BYTES      = 2 * 1024 * 1024; // 2 MB

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request, string $tenantId)
    {
        $tenant  = Tenant::findOrFail($tenantId);
        $profile = TenantBrandProfile::where('tenant_id', $tenantId)->first();
        $canEdit = $this->canEdit($tenantId);

        $healthScore   = TenantBrandProfile::computeHealthScore($tenant, $profile);
        $accentPasses  = $profile?->accent_color
            ? TenantBrandProfile::passesWcagAa($profile->accent_color)
            : true;

        return view('tenant.branding.index', compact(
            'tenant', 'profile', 'canEdit', 'healthScore', 'accentPasses'
        ));
    }

    // ── Save Draft ────────────────────────────────────────────────────────────

    public function saveDraft(Request $request, string $tenantId)
    {
        $this->requireEditAccess($tenantId);

        $data = $request->validate([
            'accent_color'  => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sidebar_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $profile = TenantBrandProfile::updateOrCreate(
            ['tenant_id' => $tenantId],
            array_filter($data, fn($v) => $v !== null) + ['status' => 'draft']
        );

        $this->logEvent('brand.draft_saved', $tenantId, ['fields_changed' => array_keys($data)]);

        return response()->json(['success' => true, 'status' => $profile->status]);
    }

    // ── Publish ───────────────────────────────────────────────────────────────

    public function publish(Request $request, string $tenantId)
    {
        $this->requireEditAccess($tenantId);

        $tenant  = Tenant::findOrFail($tenantId);
        $profile = TenantBrandProfile::firstOrNew(['tenant_id' => $tenantId]);

        if (!$profile->exists) {
            return response()->json(['error' => 'Nothing to publish — save a draft first.'], 422);
        }

        // Write canonical brand values to tenants table
        $updates = [];
        if ($profile->accent_color !== null) {
            $updates['accent_color'] = $profile->accent_color;
        }
        if ($profile->logo_url !== null) {
            $updates['logo_url'] = $profile->logo_url;
        }
        if (!empty($updates)) {
            $tenant->update($updates);
        }

        $profile->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        Cache::forget("tenant_config:{$tenantId}");
        Cache::forget("brand_profile_published:{$tenantId}");

        $freshTenant = $tenant->fresh();
        $healthScore = TenantBrandProfile::computeHealthScore($freshTenant, $profile);

        $this->snapshotVersion($tenantId, $profile, $healthScore);
        $this->logEvent('brand.published', $tenantId, ['health_score' => $healthScore]);

        return response()->json(['success' => true, 'published_at' => $profile->published_at->toIso8601String()]);
    }

    // ── Upload Logo ───────────────────────────────────────────────────────────

    public function uploadLogo(Request $request, string $tenantId)
    {
        $this->requireEditAccess($tenantId);

        $file = $request->file('logo');

        if (!$file) {
            return response()->json(['error' => 'No file received.'], 422);
        }

        // Size check
        if ($file->getSize() > self::MAX_LOGO_BYTES) {
            return response()->json(['error' => 'Logo must be 2 MB or smaller.'], 422);
        }

        // Extension check
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return response()->json(['error' => 'Only JPG, PNG, and WebP logos are accepted.'], 422);
        }

        // Server-side MIME check (finfo — not trusting the client's Content-Type)
        $realMime = $file->getMimeType();
        if (!in_array($realMime, self::ALLOWED_LOGO_MIMES, true)) {
            return response()->json(['error' => 'File content does not match an accepted image type.'], 422);
        }

        // Delete old logo from storage if one exists
        $profile = TenantBrandProfile::firstOrNew(['tenant_id' => $tenantId]);
        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        // Store with a random UUID filename so the path is not guessable
        $path = $file->storeAs(
            "tenant-logos/{$tenantId}",
            Str::uuid() . '.' . $ext,
            'public'
        );

        $url = Storage::disk('public')->url($path);

        $profile->fill([
            'logo_url'  => $url,
            'logo_path' => $path,
            'status'    => 'draft',
        ])->save();

        $this->logEvent('brand.logo_uploaded', $tenantId, ['file_size_kb' => round($file->getSize() / 1024, 1)]);

        return response()->json(['success' => true, 'logo_url' => $url]);
    }

    // ── Delete Logo ───────────────────────────────────────────────────────────

    public function deleteLogo(Request $request, string $tenantId)
    {
        $this->requireEditAccess($tenantId);

        $profile = TenantBrandProfile::where('tenant_id', $tenantId)->first();

        if (!$profile) {
            return response()->json(['success' => true]);
        }

        if ($profile->logo_path && Storage::disk('public')->exists($profile->logo_path)) {
            Storage::disk('public')->delete($profile->logo_path);
        }

        $profile->update([
            'logo_url'  => null,
            'logo_path' => null,
            'status'    => 'draft',
        ]);

        $this->logEvent('brand.logo_deleted', $tenantId, []);

        return response()->json(['success' => true]);
    }

    // ── Revert Draft ──────────────────────────────────────────────────────────

    public function revertDraft(Request $request, string $tenantId)
    {
        $this->requireEditAccess($tenantId);

        $tenant  = Tenant::findOrFail($tenantId);
        $profile = TenantBrandProfile::where('tenant_id', $tenantId)->first();

        if (!$profile || $profile->status !== 'published') {
            return response()->json(['error' => 'No published brand to revert to.'], 422);
        }

        // Revert draft fields to match the canonical published tenant values
        $profile->update([
            'accent_color' => $tenant->accent_color,
            'logo_url'     => $tenant->logo_url,
            'status'       => 'published',
        ]);

        $this->logEvent('brand.draft_reverted', $tenantId, []);

        return response()->json(['success' => true]);
    }

    // ── Version History ───────────────────────────────────────────────────────

    public function versions(Request $request, string $tenantId)
    {
        $this->requireEditAccess($tenantId);

        $versions = TenantBrandVersion::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'logo_url', 'accent_color', 'sidebar_color', 'health_score', 'created_at']);

        return response()->json($versions);
    }

    public function restoreVersion(Request $request, string $tenantId, int $versionId)
    {
        $this->requireEditAccess($tenantId);

        $version = TenantBrandVersion::where('tenant_id', $tenantId)->findOrFail($versionId);

        TenantBrandProfile::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'logo_url'     => $version->logo_url,
                'logo_path'    => $version->logo_path,
                'accent_color' => $version->accent_color,
                'sidebar_color'=> $version->sidebar_color,
                'status'       => 'draft',
            ]
        );

        $this->logEvent('brand.version_restored', $tenantId, ['version_id' => $versionId]);

        return response()->json(['success' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function canEdit(string $tenantId): bool
    {
        if (auth('web')->check()) {
            return true; // super admin
        }
        if (auth('tenant')->check()) {
            $membership = TenantMembership::where('tenant_user_id', auth('tenant')->id())
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
            return $membership && in_array($membership->role, ['owner', 'admin'], true);
        }
        return false;
    }

    private function requireEditAccess(string $tenantId): void
    {
        if (!$this->canEdit($tenantId)) {
            abort(403, 'Only Owners and Admins can modify brand settings.');
        }
    }

    private function snapshotVersion(string $tenantId, TenantBrandProfile $profile, int $healthScore): void
    {
        try {
            TenantBrandVersion::create([
                'tenant_id'    => $tenantId,
                'logo_url'     => $profile->logo_url,
                'logo_path'    => $profile->logo_path,
                'accent_color' => $profile->accent_color,
                'sidebar_color'=> $profile->sidebar_color,
                'health_score' => $healthScore,
                'created_at'   => now(),
            ]);

            // Trim to last 10
            $overflow = TenantBrandVersion::where('tenant_id', $tenantId)
                ->orderByDesc('created_at')
                ->get(['id'])
                ->pluck('id')
                ->slice(10);
            if ($overflow->isNotEmpty()) {
                TenantBrandVersion::whereIn('id', $overflow)->delete();
            }
        } catch (\Throwable) {}
    }

    private function logEvent(string $event, string $tenantId, array $extra): void
    {
        try {
            $actorId = auth('tenant')->id() ?? auth('web')->id();
            \DB::table('activity_logs')->insert([
                'description' => $event,
                'tenant_id'   => $tenantId,
                'metadata'    => json_encode(['actor_id' => $actorId, ...$extra]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } catch (\Throwable) {
            // Never block brand operations for logging failures
        }
    }
}
