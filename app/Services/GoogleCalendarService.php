<?php

namespace App\Services;

use App\Models\GoogleCalendarEvent;
use App\Models\GoogleCalendarIntegration;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    // Google Calendar color IDs
    private const COLOR_TASK        = '3';  // Grape (purple)
    private const COLOR_DEAL_URGENT = '11'; // Tomato (red) — ≤3 days left
    private const COLOR_DEAL_NORMAL = '5';  // Banana (yellow)

    // Entity type constants used across jobs, observers, and service
    public const ENTITY_TASK = 'task';
    public const ENTITY_DEAL = 'deal';

    // ── OAuth Client ─────────────────────────────────────────────────────────

    public function buildClient(): \Google\Client
    {
        $client = new \Google\Client();
        $client->setClientId(config('services.google_calendar.client_id'));
        $client->setClientSecret(config('services.google_calendar.client_secret'));
        $client->setRedirectUri(config('services.google_calendar.redirect_uri'));
        $client->addScope(\Google\Service\Calendar::CALENDAR_EVENTS);
        $client->setAccessType('offline');
        $client->setPrompt('consent');  // force refresh_token on every auth
        return $client;
    }

    public function getAuthUrl(string $state): string
    {
        $client = $this->buildClient();
        $client->setState($state);
        return $client->createAuthUrl();
    }

    public function exchangeCode(string $code): array
    {
        $client = $this->buildClient();
        return $client->fetchAccessTokenWithAuthCode($code);
    }

    public function fetchUserEmail(string $accessToken): ?string
    {
        try {
            $client = $this->buildClient();
            $client->setAccessToken(['access_token' => $accessToken, 'token_type' => 'Bearer']);
            return (new \Google\Service\Oauth2($client))->userinfo->get()->getEmail();
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Authenticated client for a stored integration ─────────────────────────

    public function clientFor(GoogleCalendarIntegration $integration): ?\Google\Client
    {
        $refreshToken = $integration->refresh_token_plain;
        if (!$refreshToken) return null;

        $client = $this->buildClient();

        // Reconstruct the token array — use actual expiry time to avoid premature refresh loops
        $expiresIn   = 3600;
        $accessToken = [
            'access_token'  => $integration->access_token_plain ?? '',
            'refresh_token' => $refreshToken,
            'expires_in'    => $expiresIn,
            'token_type'    => 'Bearer',
            'created'       => $integration->token_expires_at
                ? $integration->token_expires_at->subSeconds($expiresIn)->timestamp
                : (time() - $expiresIn * 2),
        ];
        $client->setAccessToken($accessToken);

        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($refreshToken);
            if (isset($new['error'])) {
                Log::warning('[GoogleCalendarService] Token refresh failed', ['error' => $new['error'], 'integration' => $integration->id]);
                return null;
            }
            $integration->update([
                'access_token'     => Crypt::encryptString($new['access_token']),
                'token_expires_at' => now()->addSeconds($new['expires_in'] ?? 3600),
            ]);
        }

        return $client;
    }

    // ── Task Event Sync ───────────────────────────────────────────────────────

    /**
     * Create or update a Google Calendar event for a Task.
     * Skips silently if task has no due_at or integration is missing.
     */
    public function syncTask(Task $task, GoogleCalendarIntegration $integration): void
    {
        if (!$task->due_at) return;

        $client  = $this->clientFor($integration);
        if (!$client) return;

        $service    = new \Google\Service\Calendar($client);
        $calendarId = $integration->google_calendar_id ?: 'primary';

        $event = $this->buildTaskEvent($task, $integration->tenant_id);

        $existing = GoogleCalendarEvent::where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('integration_id', $integration->id)
            ->first();

        try {
            if ($existing) {
                $service->events->update($calendarId, $existing->google_event_id, $event);
                $existing->update(['synced_at' => now()]);
            } else {
                $created = $service->events->insert($calendarId, $event);
                GoogleCalendarEvent::create([
                    'tenant_id'        => $task->tenant_id,
                    'integration_id'   => $integration->id,
                    'entity_type'      => 'task',
                    'entity_id'        => $task->id,
                    'google_event_id'  => $created->getId(),
                    'google_calendar_id' => $calendarId,
                    'synced_at'        => now(),
                ]);
            }
        } catch (\Google\Service\Exception $e) {
            // Event may have been deleted externally — create fresh
            if ($e->getCode() === 404 && $existing) {
                try {
                    $created = $service->events->insert($calendarId, $event);
                    $existing->update(['google_event_id' => $created->getId(), 'synced_at' => now()]);
                } catch (\Throwable) {}
            }
            Log::warning('[GoogleCalendarService] syncTask failed', ['task' => $task->id, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('[GoogleCalendarService] syncTask failed', ['task' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    // ── Deal Event Sync ───────────────────────────────────────────────────────

    /**
     * Create or update a Google Calendar event for a Deal (Lead).
     * Uses days_left to calculate the expiry date.
     */
    public function syncDeal(object $deal, GoogleCalendarIntegration $integration): void
    {
        $daysLeft = (int) ($deal->days_left ?? 0);
        if ($daysLeft <= 0) return;

        $client = $this->clientFor($integration);
        if (!$client) return;

        $service    = new \Google\Service\Calendar($client);
        $calendarId = $integration->google_calendar_id ?: 'primary';
        $expiryDate = Carbon::today()->addDays($daysLeft);

        $event = $this->buildDealEvent($deal, $expiryDate);

        $existing = GoogleCalendarEvent::where('entity_type', 'deal')
            ->where('entity_id', $deal->id)
            ->where('integration_id', $integration->id)
            ->first();

        try {
            if ($existing) {
                $service->events->update($calendarId, $existing->google_event_id, $event);
                $existing->update(['synced_at' => now()]);
            } else {
                $created = $service->events->insert($calendarId, $event);
                GoogleCalendarEvent::create([
                    'tenant_id'          => $deal->tenant_id,
                    'integration_id'     => $integration->id,
                    'entity_type'        => 'deal',
                    'entity_id'          => $deal->id,
                    'google_event_id'    => $created->getId(),
                    'google_calendar_id' => $calendarId,
                    'synced_at'          => now(),
                ]);
            }
        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404 && $existing) {
                try {
                    $created = $service->events->insert($calendarId, $event);
                    $existing->update(['google_event_id' => $created->getId(), 'synced_at' => now()]);
                } catch (\Throwable) {}
            }
            Log::warning('[GoogleCalendarService] syncDeal failed', ['deal' => $deal->id, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::warning('[GoogleCalendarService] syncDeal failed', ['deal' => $deal->id, 'error' => $e->getMessage()]);
        }
    }

    // ── Delete Event ──────────────────────────────────────────────────────────

    public function deleteEntityEvents(string $entityType, string $entityId): void
    {
        $records = GoogleCalendarEvent::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('integration')
            ->get();

        foreach ($records as $record) {
            if (!$record->integration) { $record->delete(); continue; }

            $client = $this->clientFor($record->integration);
            if (!$client) { $record->delete(); continue; }

            try {
                $service = new \Google\Service\Calendar($client);
                $service->events->delete(
                    $record->google_calendar_id ?: 'primary',
                    $record->google_event_id
                );
            } catch (\Throwable) {}

            $record->delete();
        }
    }

    // ── Event Builders ────────────────────────────────────────────────────────

    private function tz(): string { return config('app.timezone', 'Asia/Manila'); }

    private function buildTaskEvent(Task $task, string $tenantId): \Google\Service\Calendar\Event
    {
        $tz    = $this->tz();
        $start = $task->due_at->setTimezone($tz)->startOfDay()->addHours(6)->toRfc3339String();
        $end   = $task->due_at->setTimezone($tz)->startOfDay()->addHours(7)->toRfc3339String();
        $priorityMap = ['urgent' => '🔴', 'high' => '🟠', 'medium' => '🟡', 'low' => '🟢'];
        $icon        = $priorityMap[$task->priority] ?? '📋';
        $taskUrl     = rtrim(config('app.url'), '/') . "/tenant/{$tenantId}/tasks/{$task->id}";

        $description = implode("\n", array_filter([
            "Priority: " . ucfirst($task->priority ?? 'medium'),
            $task->description ? "Details: " . strip_tags($task->description) : null,
            '',
            "View in Referral Bunny: {$taskUrl}",
        ]));

        return new \Google\Service\Calendar\Event([
            'summary'     => "{$icon} Task: {$task->title}",
            'description' => $description,
            'colorId'     => self::COLOR_TASK,
            'start'       => ['dateTime' => $start, 'timeZone' => $tz],
            'end'         => ['dateTime' => $end,   'timeZone' => $tz],
            'source'      => [
                'title' => 'Referral Bunny',
                'url'   => $taskUrl,
            ],
        ]);
    }

    private function buildDealEvent(object $deal, Carbon $expiryDate): \Google\Service\Calendar\Event
    {
        $tz       = $this->tz();
        $start    = $expiryDate->copy()->setTimezone($tz)->startOfDay()->addHours(6)->toRfc3339String();
        $end      = $expiryDate->copy()->setTimezone($tz)->startOfDay()->addHours(7)->toRfc3339String();
        $daysLeft = (int) ($deal->days_left ?? 0);
        $isUrgent = $daysLeft <= 3;
        $colorId  = $isUrgent ? self::COLOR_DEAL_URGENT : self::COLOR_DEAL_NORMAL;
        $icon     = $isUrgent ? '🚨' : '⏰';
        $stage    = ucwords(str_replace('_', ' ', $deal->stage ?? ''));
        $dealUrl  = rtrim(config('app.url'), '/') . "/tenant/{$deal->tenant_id}/deals/{$deal->id}";

        $description = implode("\n", array_filter([
            "Referrer: " . ($deal->reseller_name ?? 'Unassigned'),
            "Stage: {$stage}",
            $deal->deal_value ? "Value: ₱" . number_format($deal->deal_value, 0) : null,
            "Days remaining: {$daysLeft}",
            '',
            "View in Referral Bunny: {$dealUrl}",
        ]));

        return new \Google\Service\Calendar\Event([
            'summary'     => "{$icon} Deal Expiry: {$deal->name}",
            'description' => $description,
            'colorId'     => $colorId,
            'start'       => ['dateTime' => $start, 'timeZone' => $tz],
            'end'         => ['dateTime' => $end,   'timeZone' => $tz],
            'source'      => [
                'title' => 'Referral Bunny',
                'url'   => $dealUrl,
            ],
        ]);
    }
}
