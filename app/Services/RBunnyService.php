<?php

namespace App\Services;

use App\Models\UserOnboardingState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * R Bunny on-site assistant service.
 *
 * Generates contextual suggestions, page help, and permission-filtered quick actions
 * for the R Bunny assistant panel. All data is tenant-scoped and role-safe.
 */
class RBunnyService
{
    // ── Page help content (title, mascot, summary, tips per page slug) ──

    private const PAGE_HELP = [
        'dashboard' => [
            'mascot'  => 'analyst',
            'title'   => 'Dashboard',
            'summary' => 'Your command center. See deals, Referrers, messages, and important alerts at a glance.',
            'tips'    => [
                'KPI cards show pipeline value, active Referrers, and conversion rate.',
                'The Daily Briefing section highlights new activity since your last visit.',
                'Use the Deal toggle to switch between Overview and Full View.',
            ],
        ],
        'deals' => [
            'mascot'  => 'thinking',
            'title'   => 'Deals',
            'summary' => 'Track every referral from first contact to payment. Add deals, move stages, and keep things organized.',
            'tips'    => [
                'Deals flow through 5 stages: Intro → Presentation → Contract → Signed → Paid.',
                'A red countdown means a deal is expiring soon — click it to take action.',
                'Switch to Kanban view for a visual pipeline overview.',
                'Click the Referrer name to see full assignment details.',
            ],
        ],
        'contacts' => [
            'mascot'  => 'helper-question',
            'title'   => 'Contacts',
            'summary' => 'Manage the people connected to your deals. Assign roles, link to deals, and invite them to the platform.',
            'tips'    => [
                'Use "Assign Role" to invite a contact as a Referrer, Manager, or Partner.',
                'Partner role always requires a deal — Partners only see their associated deal.',
                'Contacts can be linked to deals and organizations.',
                'Referrer-owned contacts are visible only to the assigned Referrer.',
                'Invitation status appears on each row — look for Pending or Active badges.',
            ],
        ],
        'organizations' => [
            'mascot'  => 'helper-question',
            'title'   => 'Organizations',
            'summary' => 'Track companies and entities associated with your deals.',
            'tips'    => [
                'For LGU IDS, each municipality is an organization.',
                'Organizations can be linked to multiple deals.',
            ],
        ],
        'messages' => [
            'mascot'  => 'waving',
            'title'   => 'Messages',
            'summary' => 'Send and receive messages with Referrers, team members, and Partners.',
            'tips'    => [
                'Use the Needs Reply tab to see threads waiting for your response.',
                'Messages tied to deals appear with the deal name for context.',
                'Partners can only message Referrers on shared active deals.',
            ],
        ],
        'imports' => [
            'mascot'  => 'tech-hologram',
            'title'   => 'Import Center',
            'summary' => 'Upload deals and contacts in bulk using the standard template.',
            'tips'    => [
                'Download the template first — it matches your current field configuration.',
                'Unmapped columns can be mapped, ignored, or saved as new fields.',
                'Preview your import before finalizing — review duplicates and warnings.',
                'LGU IDS uses a locked import format — only the standard template is accepted.',
            ],
        ],
        'reports' => [
            'mascot'  => 'analyst',
            'title'   => 'Reports',
            'summary' => 'View pipeline performance, Referrer activity, commission summaries, and deal trends.',
            'tips'    => [
                'Filter reports by date range, stage, Referrer, or status.',
                'Commission reports update automatically as deals advance to Paid.',
            ],
        ],
        'referrers' => [
            'mascot'  => 'waving',
            'title'   => 'Referrers',
            'summary' => 'Manage Referrers who submit and track deals in your program.',
            'tips'    => [
                'Invite a Referrer by email — they\'ll receive a setup link immediately.',
                'Anonymous Referrers show a placeholder name to other users.',
                'Track each Referrer\'s assigned deals and commission performance.',
            ],
        ],
        'users' => [
            'mascot'  => 'helper-question',
            'title'   => 'Users & Roles',
            'summary' => 'Manage your team members, roles, and access levels for this workspace.',
            'tips'    => [
                'Owner > Admin > Manager > Member > Viewer — each role limits what a user can see and do.',
                'Managers can have billing access enabled separately.',
                'Pending invitations show how many reminders have been sent automatically.',
            ],
        ],
        'billing' => [
            'mascot'  => 'thinking',
            'title'   => 'Billing & Usage',
            'summary' => 'Manage your subscription plan, payment method, and usage limits.',
            'tips'    => [
                'Upgrade or change plans here.',
                'Usage limits reset monthly based on your billing cycle.',
            ],
        ],
        'settings' => [
            'mascot'  => 'helper-question',
            'title'   => 'Settings',
            'summary' => 'Configure your workspace name, fields, notifications, and integrations.',
            'tips'    => [
                'Custom fields let you extend deals and contacts beyond the defaults.',
                'Notification settings control when R Bunny and email alerts fire.',
            ],
        ],
        'profile' => [
            'mascot'  => 'waving',
            'title'   => 'My Profile',
            'summary' => 'Update your name, photo, and notification preferences.',
            'tips'    => [
                'Adding a profile photo makes you easier to recognize in messages.',
                'Notification preferences control what R Bunny alerts you about.',
            ],
        ],
        // Referrer portal pages
        'reseller_deals' => [
            'mascot'  => 'rocket',
            'title'   => 'My Deals',
            'summary' => 'Your active referral deals. Track stages, deadlines, and commission status.',
            'tips'    => [
                'Create a deal to claim a municipality or opportunity.',
                'Move deals through stages as progress is made.',
                'Deals approaching expiry show a red countdown — act quickly.',
            ],
        ],
        'reseller_commission' => [
            'mascot'  => 'analyst',
            'title'   => 'Commission',
            'summary' => 'Your commission breakdown across all active and completed deals.',
            'tips'    => [
                'Commission is locked when a deal reaches the Signed stage.',
                'Paid commission appears after the deal reaches the Paid stage.',
            ],
        ],
        // Partner portal pages
        'partner_deals' => [
            'mascot'  => 'portal',
            'title'   => 'My Associated Deals',
            'summary' => 'Deals you\'ve been associated with. You can view details and message the Referrer.',
            'tips'    => [
                'You only see deals you\'ve been explicitly added to.',
                'Use Messages to communicate with the Referrer on each deal.',
            ],
        ],
    ];

    // ── Quick actions per role ─────────────────────────────────────

    private const ACTIONS = [
        // Tenant roles
        ['key' => 'open_dashboard',   'label' => 'Dashboard',       'icon' => 'home',       'url' => '/tenant/{t}/dashboard',   'roles' => ['owner','admin','manager','member']],
        ['key' => 'open_deals',       'label' => 'View Deals',      'icon' => 'deals',      'url' => '/tenant/{t}/deals',       'roles' => ['owner','admin','manager','member']],
        ['key' => 'open_contacts',    'label' => 'Contacts',        'icon' => 'contacts',   'url' => '/tenant/{t}/contacts',    'roles' => ['owner','admin','manager','member']],
        ['key' => 'open_messages',    'label' => 'Messages',        'icon' => 'message',    'url' => '/tenant/{t}/messages',    'roles' => ['owner','admin','manager','member']],
        ['key' => 'open_reports',     'label' => 'Reports',         'icon' => 'chart',      'url' => '/tenant/{t}/reports',     'roles' => ['owner','admin','manager']],
        ['key' => 'open_referrers',   'label' => 'Referrers',       'icon' => 'users',      'url' => '/tenant/{t}/referrers',   'roles' => ['owner','admin'],              'perm' => 'view_referrers'],
        ['key' => 'open_imports',     'label' => 'Imports',         'icon' => 'upload',     'url' => '/tenant/{t}/imports',     'roles' => ['owner','admin','manager'],    'perm' => 'access_import_center'],
        ['key' => 'open_users',       'label' => 'Users & Roles',   'icon' => 'team',       'url' => '/tenant/{t}/users',       'roles' => ['owner','admin']],
        ['key' => 'open_profile',     'label' => 'My Profile',      'icon' => 'user',       'url' => '/tenant/{t}/profile',     'roles' => ['owner','admin','manager','member']],
        // Referrer (reseller) portal
        ['key' => 'rs_deals',         'label' => 'My Deals',        'icon' => 'deals',      'url' => '/reseller/{t}/deals',     'roles' => ['referrer']],
        ['key' => 'rs_commission',    'label' => 'Commission',      'icon' => 'chart',      'url' => '/reseller/{t}/commission','roles' => ['referrer']],
        ['key' => 'rs_messages',      'label' => 'Messages',        'icon' => 'message',    'url' => '/reseller/{t}/messages',  'roles' => ['referrer']],
        ['key' => 'rs_profile',       'label' => 'My Profile',      'icon' => 'user',       'url' => '/reseller/{t}/profile',   'roles' => ['referrer']],
        // Partner portal
        ['key' => 'pt_deals',         'label' => 'Associated Deals','icon' => 'deals',      'url' => '/partner/deals',          'roles' => ['partner']],
        ['key' => 'pt_messages',      'label' => 'Messages',        'icon' => 'message',    'url' => '/partner/messages',       'roles' => ['partner']],
        ['key' => 'pt_profile',       'label' => 'My Profile',      'icon' => 'user',       'url' => '/partner/profile',        'roles' => ['partner']],
        // Super Admin platform
        ['key' => 'sa_dashboard',     'label' => 'Platform',        'icon' => 'home',       'url' => '/platform/dashboard',     'roles' => ['super_admin']],
        ['key' => 'sa_tenants',       'label' => 'Tenants',         'icon' => 'building',   'url' => '/platform/tenants',       'roles' => ['super_admin']],
        ['key' => 'sa_billing',       'label' => 'Billing',         'icon' => 'credit',     'url' => '/platform/billing',       'roles' => ['super_admin']],
        ['key' => 'sa_profile',       'label' => 'My Profile',      'icon' => 'user',       'url' => '/platform/profile',       'roles' => ['super_admin']],
    ];

    // ── Public API ────────────────────────────────────────────────

    public function __construct(
        private OnboardingService $onboarding,
        private PermissionService $permissions,
    ) {}

    /**
     * Full R Bunny status payload for the frontend.
     */
    public function getStatus(?string $currentPage = null): array
    {
        [$userType, $userId, $tenantId, $roleKey] = $this->resolveIdentity();
        if (! $userId) return ['enabled' => false];

        $prefs = $this->getPreferences();
        if (! $prefs['show_proactive_tips']) return ['enabled' => false];

        $suggestions  = $this->buildSuggestions($userType, $userId, $tenantId, $roleKey, $prefs);
        $quickActions = $this->buildQuickActions($roleKey, $tenantId);
        $pageHelp     = $this->resolvePageHelp($currentPage, $roleKey);
        $onboarding   = $this->onboarding->getStatus();

        $badgeCount = count(array_filter($suggestions, fn($s) => in_array($s['priority'], ['urgent', 'high'])));

        return [
            'enabled'       => true,
            'role_key'      => $roleKey,
            'tenant_id'     => $tenantId,
            'badge_count'   => $badgeCount,
            'suggestions'   => array_values($suggestions),
            'quick_actions' => $quickActions,
            'page_help'     => $pageHelp,
            'onboarding'    => $onboarding,
            'prefs'         => $prefs,
        ];
    }

    /**
     * Dismiss a suggestion key — will not show again.
     */
    public function dismiss(string $key): void
    {
        [$userType, $userId, $tenantId] = $this->resolveIdentity();
        if (! $userId) return;

        DB::table('r_bunny_suggestions')->updateOrInsert(
            [
                'user_type'      => $userType,
                'user_id'        => $userId,
                'tenant_id'      => $tenantId,
                'suggestion_key' => $key,
            ],
            ['status' => 'dismissed', 'snoozed_until' => null, 'updated_at' => now()]
        );
    }

    /**
     * Snooze a suggestion for N hours.
     */
    public function snooze(string $key, int $hours = 24): void
    {
        [$userType, $userId, $tenantId] = $this->resolveIdentity();
        if (! $userId) return;

        DB::table('r_bunny_suggestions')->updateOrInsert(
            [
                'user_type'      => $userType,
                'user_id'        => $userId,
                'tenant_id'      => $tenantId,
                'suggestion_key' => $key,
            ],
            ['status' => 'snoozed', 'snoozed_until' => now()->addHours($hours), 'updated_at' => now()]
        );
    }

    /**
     * Generate a human handoff summary.
     */
    public function handoff(?string $issue = null): array
    {
        [$userType, $userId, $tenantId, $roleKey] = $this->resolveIdentity();

        $summary = "R Bunny handoff requested.\n"
            . "Role: {$roleKey}\n"
            . "Tenant: " . ($tenantId ?? 'platform') . "\n"
            . ($issue ? "Issue: {$issue}" : '');

        return [
            'handled'   => true,
            'summary'   => $summary,
            'role'      => $roleKey,
            'tenant_id' => $tenantId,
            'message'   => "I've prepared a summary. Contact your Tenant Admin or platform support with this reference.",
            'ref'       => Str::upper(Str::random(8)),
        ];
    }

    /**
     * Get user preferences (or defaults).
     */
    public function getPreferences(): array
    {
        [$userType, $userId, $tenantId] = $this->resolveIdentity();
        if (! $userId) return $this->defaultPrefs();

        $row = DB::table('r_bunny_preferences')
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->where(fn($q) => $tenantId
                ? $q->where('tenant_id', $tenantId)
                : $q->whereNull('tenant_id')
            )
            ->first();

        if (! $row) return $this->defaultPrefs();

        return [
            'show_proactive_tips'    => (bool) $row->show_proactive_tips,
            'show_onboarding_tips'   => (bool) $row->show_onboarding_tips,
            'show_task_reminders'    => (bool) $row->show_task_reminders,
            'show_message_reminders' => (bool) $row->show_message_reminders,
            'collapsed_by_default'   => (bool) $row->collapsed_by_default,
        ];
    }

    /**
     * Update user preferences.
     */
    public function updatePreferences(array $incoming): array
    {
        [$userType, $userId, $tenantId] = $this->resolveIdentity();
        if (! $userId) return $this->defaultPrefs();

        $allowed = ['show_proactive_tips', 'show_onboarding_tips', 'show_task_reminders',
                    'show_message_reminders', 'collapsed_by_default'];

        $data = array_intersect_key($incoming, array_flip($allowed));
        $data['updated_at'] = now();

        DB::table('r_bunny_preferences')->updateOrInsert(
            [
                'user_type' => $userType,
                'user_id'   => $userId,
                'tenant_id' => $tenantId,
            ],
            array_merge($data, ['last_opened_at' => now()])
        );

        return $this->getPreferences();
    }

    // ── Private helpers ────────────────────────────────────────────

    /**
     * Build prioritized suggestion list from real DB data.
     * All queries are tenant-scoped and role-safe.
     */
    private function buildSuggestions(
        string $userType, string $userId, ?string $tenantId, string $roleKey, array $prefs
    ): array {
        $dismissed = $this->getDismissedKeys($userType, $userId, $tenantId);
        $suggestions = [];

        // ── Expiring deals (tenant roles + referrer) ──────────────
        if ($tenantId && in_array($roleKey, ['owner', 'admin', 'manager', 'member'])) {
            $expiring = DB::table('leads')
                ->where('tenant_id', $tenantId)
                ->where(fn($q) => $q->where('status', 'expiring')
                    ->orWhere(fn($q2) => $q2->where('days_left', '<=', 3)->where('status', 'active')))
                ->count();

            if ($expiring > 0 && ! in_array('expiring_deals', $dismissed)) {
                $suggestions[] = [
                    'key'      => 'expiring_deals',
                    'priority' => 'high',
                    'mascot'   => 'warning-error',
                    'title'    => $expiring === 1 ? '1 deal is expiring soon' : "{$expiring} deals are expiring soon",
                    'body'     => 'These deals need attention before they expire.',
                    'action'   => ['label' => 'Review Expiring Deals', 'url' => "/tenant/{$tenantId}/deals?status=expiring"],
                ];
            }
        }

        // ── Unread messages ───────────────────────────────────────
        if ($prefs['show_message_reminders'] && $tenantId &&
            in_array($roleKey, ['owner', 'admin', 'manager', 'member'])) {
            $unread = DB::table('message_threads as t')
                ->where('t.tenant_id', $tenantId)
                ->whereExists(fn($q) => $q->select(DB::raw(1))
                    ->from('thread_messages as m')
                    ->whereColumn('m.thread_id', 't.id')
                    ->where('m.is_read', false)
                    ->where('m.sender_type', '!=', 'tenant_admin')
                )
                ->count();

            if ($unread > 0 && ! in_array('unread_messages', $dismissed)) {
                $suggestions[] = [
                    'key'      => 'unread_messages',
                    'priority' => 'normal',
                    'mascot'   => 'helper-question',
                    'title'    => $unread === 1 ? '1 unread message' : "{$unread} unread messages",
                    'body'     => 'You have messages waiting for a reply.',
                    'action'   => ['label' => 'Open Messages', 'url' => "/tenant/{$tenantId}/messages"],
                ];
            }
        }

        // ── Pending invitations expiring soon ─────────────────────
        if ($tenantId && in_array($roleKey, ['owner', 'admin'])) {
            $expiringInvites = DB::table('tenant_invitations')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->where('expires_at', '<', now()->addDays(2))
                ->count();

            if ($expiringInvites > 0 && ! in_array('expiring_invites', $dismissed)) {
                $suggestions[] = [
                    'key'      => 'expiring_invites',
                    'priority' => 'normal',
                    'mascot'   => 'thinking',
                    'title'    => 'Invitations expiring soon',
                    'body'     => "{$expiringInvites} pending invitation" . ($expiringInvites > 1 ? 's' : '') . " will expire in under 2 days.",
                    'action'   => ['label' => 'Review Invitations', 'url' => "/tenant/{$tenantId}/users"],
                ];
            }
        }

        // ── Referrer: their own deals expiring ────────────────────
        if ($userType === 'reseller' && $tenantId) {
            $reseller = Auth::guard('reseller')->user();
            if ($reseller) {
                $expiring = DB::table('leads')
                    ->where('tenant_id', $tenantId)
                    ->where('reseller_name', $reseller->name)
                    ->where(fn($q) => $q->where('status', 'expiring')
                        ->orWhere(fn($q2) => $q2->where('days_left', '<=', 3)->where('status', 'active')))
                    ->count();

                if ($expiring > 0 && ! in_array('rs_expiring_deals', $dismissed)) {
                    $suggestions[] = [
                        'key'      => 'rs_expiring_deals',
                        'priority' => 'high',
                        'mascot'   => 'warning-error',
                        'title'    => $expiring === 1 ? '1 of your deals is expiring soon' : "{$expiring} of your deals are expiring",
                        'body'     => 'Act before the deadline — expired deals may be reassigned.',
                        'action'   => ['label' => 'Review My Deals', 'url' => "/reseller/{$tenantId}/deals"],
                    ];
                }
            }
        }

        // ── Partner: no associated deals viewed ───────────────────
        if ($userType === 'partner' && $tenantId) {
            $partner = Auth::guard('partner')->user();
            if ($partner) {
                $hasDeal = DB::table('deal_partners')
                    ->where('partner_user_id', $partner->id)
                    ->where('status', 'active')
                    ->exists();

                if ($hasDeal && ! in_array('pt_view_deal', $dismissed)) {
                    $suggestions[] = [
                        'key'      => 'pt_view_deal',
                        'priority' => 'normal',
                        'mascot'   => 'portal',
                        'title'    => 'You have associated deals',
                        'body'     => 'Open your deal list to see what you\'re connected to.',
                        'action'   => ['label' => 'View My Deals', 'url' => '/partner/deals'],
                    ];
                }
            }
        }

        // ── Onboarding: next pending task ─────────────────────────
        if ($prefs['show_onboarding_tips']) {
            try {
                $onboardingStatus = $this->onboarding->getStatus();
                if (($onboardingStatus['next_task'] ?? null) && ! ($onboardingStatus['progress']['fully_ready'] ?? false)) {
                    $nextTask = $onboardingStatus['next_task'];
                    $key = 'onboarding_' . $nextTask['key'];
                    if (! in_array($key, $dismissed)) {
                        $suggestions[] = [
                            'key'      => $key,
                            'priority' => 'low',
                            'mascot'   => $nextTask['mascot'] ?? 'helper-question',
                            'title'    => 'Setup: ' . $nextTask['label'],
                            'body'     => $nextTask['message'] ?? 'Complete this step to finish your setup.',
                            'action'   => $nextTask['action']
                                ? ['label' => 'Go there →', 'url' => $nextTask['action']]
                                : null,
                        ];
                    }
                }
            } catch (\Throwable) {}
        }

        // Sort by priority
        $order = ['urgent' => 0, 'high' => 1, 'normal' => 2, 'low' => 3];
        usort($suggestions, fn($a, $b) => ($order[$a['priority']] ?? 9) <=> ($order[$b['priority']] ?? 9));

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Return permission-filtered quick actions for the role.
     */
    private function buildQuickActions(string $roleKey, ?string $tenantId): array
    {
        $membership = null;
        if ($tenantId && Auth::guard('tenant')->check()) {
            $userId    = Auth::guard('tenant')->id();
            $membership = \App\Models\TenantMembership::where('tenant_user_id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->first();
        }

        $actions = [];
        foreach (self::ACTIONS as $action) {
            if (! in_array($roleKey, $action['roles'])) continue;

            // Permission check for tenant roles
            if (isset($action['perm']) && $membership) {
                if (! $this->permissions->can($membership, $action['perm'])) continue;
            }

            $url = str_replace('{t}', $tenantId ?? '', $action['url']);
            $actions[] = [
                'key'   => $action['key'],
                'label' => $action['label'],
                'icon'  => $action['icon'],
                'url'   => $url,
            ];
        }

        return $actions;
    }

    /**
     * Return page-specific help content based on the current URL path.
     */
    private function resolvePageHelp(?string $page, string $roleKey): ?array
    {
        if (! $page) return null;

        $slug = $this->detectPageSlug($page, $roleKey);
        return $slug ? (self::PAGE_HELP[$slug] ?? null) : null;
    }

    private function detectPageSlug(string $page, string $roleKey): ?string
    {
        $map = [
            '/dashboard'    => 'dashboard',
            '/deals'        => $roleKey === 'referrer' ? 'reseller_deals' : ($roleKey === 'partner' ? 'partner_deals' : 'deals'),
            '/contacts'     => 'contacts',
            '/organizations'=> 'organizations',
            '/messages'     => 'messages',
            '/imports'      => 'imports',
            '/reports'      => 'reports',
            '/referrers'    => 'referrers',
            '/users'        => 'users',
            '/billing'      => 'billing',
            '/settings'     => 'settings',
            '/profile'      => 'profile',
            '/commission'   => 'reseller_commission',
        ];

        foreach ($map as $pattern => $slug) {
            if (str_contains($page, $pattern)) return $slug;
        }
        return null;
    }

    private function getDismissedKeys(string $userType, string $userId, ?string $tenantId): array
    {
        return DB::table('r_bunny_suggestions')
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->where(fn($q) => $tenantId
                ? $q->where('tenant_id', $tenantId)
                : $q->whereNull('tenant_id')
            )
            ->where(fn($q) => $q
                ->where('status', 'dismissed')
                ->orWhere(fn($q2) => $q2->where('status', 'snoozed')
                    ->where('snoozed_until', '>', now()))
            )
            ->pluck('suggestion_key')
            ->toArray();
    }

    private function defaultPrefs(): array
    {
        return [
            'show_proactive_tips'    => true,
            'show_onboarding_tips'   => true,
            'show_task_reminders'    => true,
            'show_message_reminders' => true,
            'collapsed_by_default'   => false,
        ];
    }

    /**
     * Resolve the current authenticated user's identity.
     * Returns [userType, userId, tenantId, roleKey].
     */
    private function resolveIdentity(): array
    {
        if (Auth::guard('web')->check()) {
            $u = Auth::guard('web')->user();
            return ['super_admin', (string) $u->id, null, 'super_admin'];
        }

        if (Auth::guard('tenant')->check()) {
            $u = Auth::guard('tenant')->user();

            $tenantId = request()->route('tenantId')
                ?? request()->input('tenant_id')
                ?? request()->query('tenant_id');

            if ($tenantId) {
                $valid = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $u->id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->exists();
                if (! $valid) $tenantId = null;
            }

            if (! $tenantId) {
                $tenantId = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $u->id)
                    ->where('status', 'active')
                    ->value('tenant_id');
            }

            $role = $tenantId ? DB::table('tenant_memberships')
                ->where('tenant_user_id', $u->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role') : null;

            return ['tenant_user', (string) $u->id, $tenantId, $role ?? 'member'];
        }

        if (Auth::guard('reseller')->check()) {
            $r = Auth::guard('reseller')->user();
            return ['reseller', (string) $r->id, $r->tenant_id, 'referrer'];
        }

        if (Auth::guard('partner')->check()) {
            $p = Auth::guard('partner')->user();
            return ['partner', (string) $p->id, $p->tenant_id, 'partner'];
        }

        return [null, null, null, null];
    }
}
