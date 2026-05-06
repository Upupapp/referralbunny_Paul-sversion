<?php

namespace App\Services;

use App\Models\UserOnboardingState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    // ── Role-based walkthrough steps (max 5 per role) ─────────────

    private const WALKTHROUGHS = [
        'super_admin' => [
            ['mascot' => 'analyst',     'title' => 'Welcome to Mission Control',       'body' => 'R Bunny will help you monitor tenants, subscriptions, platform activity, and important alerts from one place.'],
            ['mascot' => 'helper-question',      'title' => 'Track every tenant safely',         'body' => 'View tenant activity, billing status, onboarding progress, and platform health without mixing tenant data.'],
            ['mascot' => 'thinking',    'title' => 'Find what you need fast',           'body' => 'Use platform search and tenant filters to locate records, review issues, and support users with proper access controls.'],
            ['mascot' => 'waving',      'title' => 'Stay ahead of what needs action',  'body' => 'R Bunny can remind you about pending approvals, billing alerts, imports, and system notifications.'],
            ['mascot' => 'thumbs-up',    'title' => 'Ready to manage the platform',     'body' => 'Your dashboard gives you the big picture. R Bunny will stay nearby if anything needs your attention.'],
        ],
        'owner' => [
            ['mascot' => 'hero-flying',        'title' => 'Welcome to your referral command center', 'body' => 'R Bunny will help you manage deals, Referrers, Partners, contacts, messages, and reports for your tenant.'],
            ['mascot' => 'analyst',     'title' => 'Start with your daily briefing',          'body' => 'See new deals, expiring deals, active Referrers, unread messages, and important tasks from one clean dashboard.'],
            ['mascot' => 'thinking',    'title' => 'Track the right people and opportunities','body' => 'Add deals, import contacts, assign Referrers, and keep every opportunity moving.'],
            ['mascot' => 'waving',      'title' => 'Invite the right people',                 'body' => 'Invite Managers, Staff, Referrers, and Partners while keeping permissions safe and tenant-scoped.'],
            ['mascot' => 'celebration', 'title' => "You're ready to build your referral engine", 'body' => "R Bunny will guide your next actions, remind you what needs attention, and help you complete setup."],
        ],
        'admin' => [
            ['mascot' => 'hero-flying',        'title' => 'Welcome to your referral command center', 'body' => 'R Bunny will help you manage deals, Referrers, Partners, contacts, messages, and reports for your tenant.'],
            ['mascot' => 'analyst',     'title' => 'Start with your daily briefing',          'body' => 'See new deals, expiring deals, active Referrers, unread messages, and important tasks from one clean dashboard.'],
            ['mascot' => 'thinking',    'title' => 'Track the right people and opportunities','body' => 'Add deals, import contacts, assign Referrers, and keep every opportunity moving.'],
            ['mascot' => 'waving',      'title' => 'Invite the right people',                 'body' => 'Invite Managers, Staff, Referrers, and Partners while keeping permissions safe and tenant-scoped.'],
            ['mascot' => 'celebration', 'title' => "You're ready to build your referral engine", 'body' => "R Bunny will guide your next actions, remind you what needs attention, and help you complete setup."],
        ],
        'manager' => [
            ['mascot' => 'waving',      'title' => 'Welcome to your Manager workspace',   'body' => 'You can help manage daily operations for this tenant based on the permissions assigned to you.'],
            ['mascot' => 'analyst',     'title' => 'Your dashboard shows what needs attention', 'body' => 'Track deals, contacts, messages, reports, and reminders in one place.'],
            ['mascot' => 'helper-question',      'title' => 'R Bunny keeps your access clear',     'body' => 'Some sensitive areas like billing or account deletion may be restricted unless the Tenant Admin allows access.'],
            ['mascot' => 'thinking',    'title' => 'Help move deals forward',             'body' => 'Review deal activity, update records, manage contacts, and respond to messages where your role allows.'],
            ['mascot' => 'thumbs-up',    'title' => "You're ready to help manage",         'body' => "R Bunny will suggest your first tasks and guide you if something needs action."],
        ],
        'member' => [
            ['mascot' => 'waving',      'title' => 'Welcome to the team',               'body' => 'R Bunny will show you the tools available for your role.'],
            ['mascot' => 'helper-question',      'title' => 'Focus on what you\'re allowed to manage', 'body' => 'Your access may include deals, contacts, messages, reports, or specific tasks assigned by your admin.'],
            ['mascot' => 'thumbs-up',    'title' => 'Make your profile easy to recognize','body' => 'Add your name, nickname, and photo so teammates know who they are working with.'],
            ['mascot' => 'waving',      'title' => 'Never miss important updates',       'body' => 'Turn on notifications so R Bunny can alert you when something needs your attention.'],
            ['mascot' => 'celebration', 'title' => "You're ready to work",              'body' => "R Bunny will suggest next steps based on your permissions."],
        ],
        'referrer' => [
            ['mascot' => 'rocket',      'title' => 'Welcome to your referral workspace',  'body' => 'R Bunny will help you submit deals, track progress, view commissions, and stay updated.'],
            ['mascot' => 'analyst',     'title' => 'Track every deal you\'re working on', 'body' => 'See your active deals, stages, updates, and deadlines from your dashboard.'],
            ['mascot' => 'hero-flying',        'title' => 'Start referring opportunities',        'body' => 'Create your first deal or claim an available opportunity based on tenant rules.'],
            ['mascot' => 'waving',      'title' => 'Work with your deal contacts',        'body' => 'View Partners connected to your deals and respond to messages when action is needed.'],
            ['mascot' => 'celebration', 'title' => 'Happy referring!',                    'body' => "R Bunny will guide your first actions and remind you when a deal needs movement."],
        ],
        'partner' => [
            ['mascot' => 'portal',      'title' => 'Welcome to your Partner view',        'body' => "R Bunny will help you view the deals you're associated with and message the Referrers connected to those deals."],
            ['mascot' => 'helper-question',      'title' => 'Limited and focused access',          'body' => 'Your account is designed for deal-specific visibility. You can view associated deals and relevant messages only.'],
            ['mascot' => 'thinking',    'title' => 'Check your connected deals',          'body' => "Open your deal list to see details you're allowed to view."],
            ['mascot' => 'waving',      'title' => 'Stay connected',                      'body' => 'You can message Referrers you share an active deal with.'],
            ['mascot' => 'thumbs-up',    'title' => "R Bunny will stay nearby",            'body' => "Once your profile is complete, you're ready. Come back to R Bunny anytime for help."],
        ],
    ];

    // ── Role-based onboarding tasks ────────────────────────────────

    private const TASKS = [
        'super_admin' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '/platform/profile',        'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Review platform notifications',    'action' => '/platform/dashboard',      'required' => false,'order' => 3],
            ['key' => 'tenant_reviewed',        'label' => 'Review tenant list',               'action' => '/platform/tenants',        'required' => false,'order' => 4],
            ['key' => 'billing_reviewed',       'label' => 'Check billing overview',           'action' => '/platform/billing',        'required' => false,'order' => 5],
        ],
        'owner' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '{tenant}/profile',         'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Enable browser notifications',     'action' => '{tenant}/settings',        'required' => false,'order' => 3],
            ['key' => 'first_deal',             'label' => 'Add your first deal',              'action' => '{tenant}/deals',           'required' => true, 'order' => 4],
            ['key' => 'first_contact',          'label' => 'Add your first contact',           'action' => '{tenant}/contacts',        'required' => false,'order' => 5],
            ['key' => 'first_invite',           'label' => 'Invite your first Referrer',       'action' => '{tenant}/referrers',       'required' => false,'order' => 6],
            ['key' => 'first_user_invite',      'label' => 'Invite a Team Manager',            'action' => '{tenant}/users',           'required' => false,'order' => 7],
            ['key' => 'messages_checked',       'label' => 'Check your message inbox',         'action' => '{tenant}/messages',        'required' => false,'order' => 8],
        ],
        'admin' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '{tenant}/profile',         'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Enable browser notifications',     'action' => '{tenant}/settings',        'required' => false,'order' => 3],
            ['key' => 'first_deal',             'label' => 'Add your first deal',              'action' => '{tenant}/deals',           'required' => true, 'order' => 4],
            ['key' => 'first_contact',          'label' => 'Add your first contact',           'action' => '{tenant}/contacts',        'required' => false,'order' => 5],
            ['key' => 'first_invite',           'label' => 'Invite your first Referrer',       'action' => '{tenant}/referrers',       'required' => false,'order' => 6],
            ['key' => 'messages_checked',       'label' => 'Check your message inbox',         'action' => '{tenant}/messages',        'required' => false,'order' => 7],
        ],
        'manager' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '{tenant}/profile',         'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Enable browser notifications',     'action' => '{tenant}/settings',        'required' => false,'order' => 3],
            ['key' => 'deals_reviewed',         'label' => 'Review recent deals',              'action' => '{tenant}/deals',           'required' => false,'order' => 4],
            ['key' => 'first_contact',          'label' => 'Add your first contact',           'action' => '{tenant}/contacts',        'required' => false,'order' => 5],
            ['key' => 'messages_checked',       'label' => 'Check your message inbox',         'action' => '{tenant}/messages',        'required' => false,'order' => 6],
        ],
        'member' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '{tenant}/profile',         'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Enable browser notifications',     'action' => null,                       'required' => false,'order' => 3],
            ['key' => 'messages_checked',       'label' => 'Check your messages',              'action' => '{tenant}/messages',        'required' => false,'order' => 4],
            ['key' => 'deals_reviewed',         'label' => 'Review your assigned deals',       'action' => '{tenant}/deals',           'required' => false,'order' => 5],
        ],
        'referrer' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '{reseller}/profile',       'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Enable browser notifications',     'action' => null,                       'required' => false,'order' => 3],
            ['key' => 'first_deal',             'label' => 'Add your first deal',              'action' => '{reseller}/deals',         'required' => true, 'order' => 4],
            ['key' => 'first_contact',          'label' => 'Add your first contact',           'action' => '{reseller}/contacts/imports','required' => false,'order' => 5],
            ['key' => 'commission_reviewed',    'label' => 'Review your commission page',      'action' => '{reseller}/commission',    'required' => false,'order' => 6],
            ['key' => 'messages_checked',       'label' => 'Check your messages',              'action' => '{reseller}/messages',      'required' => false,'order' => 7],
        ],
        'partner' => [
            ['key' => 'walkthrough_done',       'label' => 'Complete the welcome tour',        'action' => null,                       'required' => true, 'order' => 1],
            ['key' => 'profile_complete',       'label' => 'Complete your profile',            'action' => '/partner/profile',         'required' => true, 'order' => 2],
            ['key' => 'notifications_reviewed', 'label' => 'Enable browser notifications',     'action' => null,                       'required' => false,'order' => 3],
            ['key' => 'deal_viewed',            'label' => 'View your associated deal',        'action' => '/partner/deals',           'required' => true, 'order' => 4],
            ['key' => 'messages_checked',       'label' => 'Send a message to your Referrer',  'action' => '/partner/messages',        'required' => false,'order' => 5],
        ],
    ];

    // ── R Bunny bot suggestion messages ───────────────────────────

    private const SUGGESTIONS = [
        'walkthrough_done'       => ['mascot' => 'helper-question',      'msg' => "Nice! You finished the quick tour. Want to complete your setup next?"],
        'profile_complete'       => ['mascot' => 'waving',      'msg' => "Your profile is almost ready. Add a photo or nickname so people can recognize you faster."],
        'notifications_reviewed' => ['mascot' => 'helper-question',      'msg' => "Want to stay on top of things? Enable browser notifications so R Bunny can alert you in real time."],
        'first_deal'             => ['mascot' => 'rocket',      'msg' => "Ready for your first move? Add your first deal and I'll help you keep it organized."],
        'first_contact'          => ['mascot' => 'helper-question',      'msg' => "Contacts make deals easier to track. Want to add your first contact?"],
        'first_invite'           => ['mascot' => 'waving',      'msg' => "Your referral program works better with people. Want to invite your first Referrer?"],
        'first_user_invite'      => ['mascot' => 'waving',      'msg' => "Ready to grow your team? Invite a Manager to help run daily operations."],
        'messages_checked'       => ['mascot' => 'helper-question',      'msg' => "You have messages waiting. Want to check them now?"],
        'deals_reviewed'         => ['mascot' => 'analyst',     'msg' => "Take a look at your deal pipeline to see what needs attention."],
        'commission_reviewed'    => ['mascot' => 'analyst',     'msg' => "Your commission page shows your earnings and status. Take a peek!"],
        'deal_viewed'            => ['mascot' => 'portal',      'msg' => "Your deal access is ready. Want to open your first associated deal?"],
        'tenant_reviewed'        => ['mascot' => 'analyst',     'msg' => "Take a look at your tenant list to get a platform overview."],
        'billing_reviewed'       => ['mascot' => 'helper-question',      'msg' => "Review the billing dashboard for any alerts or pending items."],
    ];

    // ── Public API ─────────────────────────────────────────────────

    /**
     * Get or create onboarding state for the current authenticated user.
     */
    public function getState(): ?UserOnboardingState
    {
        [$userType, $userId, $tenantId, $roleKey] = $this->resolveIdentity();
        if (! $userId) return null;

        return UserOnboardingState::firstOrCreate(
            [
                'user_type' => $userType,
                'user_id'   => $userId,
                'tenant_id' => $tenantId,
            ],
            [
                'role_key'           => $roleKey,
                'walkthrough_status' => 'not_started',
                'walkthrough_step'   => 0,
                'completed_tasks'    => [],
                'dismissed_prompts'  => [],
                'first_seen_at'      => now(),
            ]
        );
    }

    /**
     * Full onboarding status payload for the frontend.
     */
    public function getStatus(): array
    {
        $state = $this->getState();
        if (! $state) return ['enabled' => false];

        $tasks     = $this->getTasksForRole($state->role_key, $state->tenant_id);
        $completed = $state->completed_tasks ?? [];

        // Auto-detect profile completion
        $this->autoDetectCompletions($state);
        $completed = $state->completed_tasks ?? [];

        $done  = count(array_filter($tasks, fn($t) => in_array($t['key'], $completed)));
        $total = count($tasks);
        $pct   = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        $requiredDone  = count(array_filter($tasks, fn($t) => $t['required'] && in_array($t['key'], $completed)));
        $requiredTotal = count(array_filter($tasks, fn($t) => $t['required']));
        $fullyReady    = $requiredTotal > 0 && $requiredDone >= $requiredTotal;

        if ($fullyReady && ! $state->is_fully_ready) {
            $state->is_fully_ready  = true;
            $state->fully_ready_at  = now();
            $state->save();
        }

        $nextTask     = $this->nextPendingTask($tasks, $completed, $state);
        $nextSuggestion = $nextTask ? (self::SUGGESTIONS[$nextTask['key']] ?? null) : null;

        $profilePct = $this->profileCompletionPercent();

        return [
            'enabled'          => true,
            'role_key'         => $state->role_key,
            'walkthrough'      => [
                'status'       => $state->walkthrough_status,
                'step'         => $state->walkthrough_step,
                'steps'        => $this->walkthroughSteps($state->role_key),
                'total_steps'  => count($this->walkthroughSteps($state->role_key)),
            ],
            'tasks'            => array_map(fn($t) => [
                ...$t,
                'completed' => in_array($t['key'], $completed),
                'dismissed' => $state->isPromptDismissed($t['key']),
            ], $tasks),
            'progress'         => [
                'done'        => $done,
                'total'       => $total,
                'percent'     => $pct,
                'fully_ready' => $fullyReady,
            ],
            'profile_progress' => $profilePct,
            'is_snoozed'       => $state->isSnoozed(),
            'snoozed_until'    => $state->snoozed_until?->toISOString(),
            'next_task'        => $nextTask ? [
                ...$nextTask,
                'mascot'  => $nextSuggestion['mascot'] ?? 'helper',
                'message' => $nextSuggestion['msg']    ?? '',
            ] : null,
        ];
    }

    /**
     * Start or resume walkthrough.
     */
    public function startWalkthrough(): array
    {
        $state = $this->getState();
        if (! $state) return [];

        if ($state->walkthrough_status === 'not_started') {
            $state->walkthrough_status = 'in_progress';
            $state->walkthrough_step   = 0;
            $state->save();
        }

        return $this->getStatus();
    }

    /**
     * Advance walkthrough step.
     */
    public function advanceWalkthrough(int $step): array
    {
        $state = $this->getState();
        if (! $state) return [];

        $total = count($this->walkthroughSteps($state->role_key));
        $state->walkthrough_step = min($step, $total - 1);
        $state->save();

        return $this->getStatus();
    }

    /**
     * Complete walkthrough (all steps done).
     */
    public function completeWalkthrough(): array
    {
        $state = $this->getState();
        if (! $state) return [];

        $state->walkthrough_status       = 'completed';
        $state->walkthrough_completed_at = now();
        $state->markTaskComplete('walkthrough_done');
        $state->save();

        return $this->getStatus();
    }

    /**
     * Skip walkthrough.
     */
    public function skipWalkthrough(): array
    {
        $state = $this->getState();
        if (! $state) return [];

        if (! in_array($state->walkthrough_status, ['completed'])) {
            $state->walkthrough_status = 'skipped';
            $state->save();
        }

        return $this->getStatus();
    }

    /**
     * Complete a specific task.
     */
    public function completeTask(string $taskKey): array
    {
        $state = $this->getState();
        if (! $state) return [];

        $state->markTaskComplete($taskKey);
        return $this->getStatus();
    }

    /**
     * Dismiss a task suggestion (won't show in bot, still shows in list).
     */
    public function dismissTask(string $taskKey): array
    {
        $state = $this->getState();
        if (! $state) return [];

        $state->dismissPrompt($taskKey);
        $state->last_prompted_at = now();
        $state->save();
        return $this->getStatus();
    }

    /**
     * Snooze all onboarding prompts.
     */
    public function snooze(int $hours = 24): array
    {
        $state = $this->getState();
        if (! $state) return [];

        $state->snoozed_until    = now()->addHours($hours);
        $state->last_prompted_at = now();
        $state->save();
        return $this->getStatus();
    }

    /**
     * Clear the snooze (wake up) without changing anything else.
     */
    public function clearSnooze(): array
    {
        $state = $this->getState();
        if (! $state) return [];

        $state->snoozed_until = null;
        $state->save();
        return $this->getStatus();
    }

    // ── Helpers ────────────────────────────────────────────────────

    public function walkthroughSteps(string $roleKey): array
    {
        return self::WALKTHROUGHS[$roleKey] ?? self::WALKTHROUGHS['member'];
    }

    private function getTasksForRole(string $roleKey, ?string $tenantId): array
    {
        $tasks = self::TASKS[$roleKey] ?? self::TASKS['member'];

        return array_map(function (array $task) use ($tenantId) {
            if ($tenantId && $task['action']) {
                $task['action'] = str_replace(
                    ['{tenant}', '{reseller}'],
                    ["/tenant/{$tenantId}", "/reseller/{$tenantId}"],
                    $task['action']
                );
            }
            return $task;
        }, $tasks);
    }

    private function nextPendingTask(array $tasks, array $completed, UserOnboardingState $state): ?array
    {
        // Skip if snoozed
        if ($state->isSnoozed()) return null;

        foreach ($tasks as $task) {
            if (! in_array($task['key'], $completed) && ! $state->isPromptDismissed($task['key'])) {
                return $task;
            }
        }
        return null;
    }

    /**
     * Auto-detect which tasks are already complete based on real data.
     * Only marks complete — never un-marks.
     */
    private function autoDetectCompletions(UserOnboardingState $state): void
    {
        $role     = $state->role_key;
        $tenantId = $state->tenant_id;

        // Profile completion check
        if (! $state->hasCompletedTask('profile_complete')) {
            if ($this->isProfileComplete($state)) {
                $state->markTaskComplete('profile_complete');
            }
        }

        // Role-specific auto-detects
        if ($tenantId && in_array($role, ['owner', 'admin', 'manager', 'member'])) {
            if (! $state->hasCompletedTask('first_deal')) {
                $hasDeals = DB::table('leads')->where('tenant_id', $tenantId)->exists();
                if ($hasDeals) $state->markTaskComplete('first_deal');
            }
            if (! $state->hasCompletedTask('first_contact')) {
                $hasContacts = DB::table('contacts')->where('tenant_id', $tenantId)->exists();
                if ($hasContacts) $state->markTaskComplete('first_contact');
            }
            if (! $state->hasCompletedTask('first_invite')) {
                $hasInvite = DB::table('resellers')->where('tenant_id', $tenantId)->exists();
                if ($hasInvite) $state->markTaskComplete('first_invite');
            }
        }

        if ($tenantId && $state->user_type === 'reseller') {
            if (! $state->hasCompletedTask('first_deal')) {
                $reseller = Auth::guard('reseller')->user();
                if ($reseller) {
                    $hasDeals = DB::table('leads')
                        ->where('tenant_id', $tenantId)
                        ->where('reseller_name', $reseller->name)
                        ->exists();
                    if ($hasDeals) $state->markTaskComplete('first_deal');
                }
            }
        }

        if ($state->user_type === 'partner') {
            if (! $state->hasCompletedTask('deal_viewed')) {
                $partner = Auth::guard('partner')->user();
                if ($partner) {
                    $hasDeal = DB::table('deal_partners')
                        ->where('partner_user_id', $partner->id)
                        ->where('status', 'active')
                        ->exists();
                    if ($hasDeal) $state->markTaskComplete('deal_viewed');
                }
            }
        }

        // Walkthrough done detection
        if (! $state->hasCompletedTask('walkthrough_done') &&
            in_array($state->walkthrough_status, ['completed', 'skipped'])) {
            $state->markTaskComplete('walkthrough_done');
        }
    }

    private function isProfileComplete(UserOnboardingState $state): bool
    {
        return match($state->user_type) {
            'tenant_user' => $this->tenantUserProfileComplete(),
            'reseller'    => $this->resellerProfileComplete(),
            'partner'     => $this->partnerProfileComplete(),
            'super_admin' => true,
            default       => false,
        };
    }

    private function tenantUserProfileComplete(): bool
    {
        $user = Auth::guard('tenant')->user();
        return $user && $user->first_name && $user->last_name;
    }

    private function resellerProfileComplete(): bool
    {
        $r = Auth::guard('reseller')->user();
        return $r && $r->name && $r->email;
    }

    private function partnerProfileComplete(): bool
    {
        $p = Auth::guard('partner')->user();
        return $p && $p->first_name && $p->last_name && $p->isSetupComplete();
    }

    public function profileCompletionPercent(): int
    {
        $items    = 0;
        $complete = 0;

        if ($user = Auth::guard('tenant')->user()) {
            $checks = [
                (bool) $user->first_name,
                (bool) $user->last_name,
                (bool) $user->email,
            ];
            $items    = count($checks);
            $complete = count(array_filter($checks));
        } elseif ($r = Auth::guard('reseller')->user()) {
            $checks = [
                (bool) $r->name,
                (bool) $r->email,
                (bool) $r->profile_photo_path,
            ];
            $items    = count($checks);
            $complete = count(array_filter($checks));
        } elseif ($p = Auth::guard('partner')->user()) {
            $checks = [
                (bool) $p->first_name,
                (bool) $p->last_name,
                (bool) $p->email,
                (bool) $p->setup_completed_at,
            ];
            $items    = count($checks);
            $complete = count(array_filter($checks));
        } elseif (Auth::guard('web')->check()) {
            return 100;
        }

        return $items > 0 ? (int) round(($complete / $items) * 100) : 0;
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

            // Priority: route param → request body/query (validated below) → first active membership
            $requestedTenantId = request()->route('tenantId')
                ?? request()->input('tenant_id')
                ?? request()->query('tenant_id');

            if ($requestedTenantId) {
                // Validate the user actually belongs to this tenant (prevent tenant-hopping)
                $membership = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $u->id)
                    ->where('tenant_id', $requestedTenantId)
                    ->where('status', 'active')
                    ->first();
                $tenantId = $membership ? $requestedTenantId : null;
            } else {
                $tenantId = null;
            }

            // Final fallback: first active membership
            if (! $tenantId) {
                $tenantId = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $u->id)
                    ->where('status', 'active')
                    ->value('tenant_id');
            }

            $roleKey = 'member';
            if ($tenantId) {
                $role    = DB::table('tenant_memberships')
                    ->where('tenant_user_id', $u->id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->value('role');
                $roleKey = $role ?? 'member';
            }

            return ['tenant_user', (string) $u->id, $tenantId, $roleKey];
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
