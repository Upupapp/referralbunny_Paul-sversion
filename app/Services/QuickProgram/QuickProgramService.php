<?php
namespace App\Services\QuickProgram;

use App\Models\{Program, ProgramOffer, ProgramOfferVersion, ProgramConnection, Tenant, TenantReferralProgramDraft, TenantReferralProgramVersion};
use App\Services\Programs\ProgramLifecycleService;
use App\Services\ReferralProgram\ReferralProgramSetupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuickProgramService
{
    public function hasProgram(string $tenantId): bool
    {
        return Program::forTenant($tenantId)->where(function ($q) {
            $q->whereNotNull('launched_at')->orWhereIn('status', ['active', 'paused', 'scheduled', 'ended', 'archived']);
        })->exists() || TenantReferralProgramVersion::where('tenant_id', $tenantId)->exists();
    }

    public function draft(string $tenantId): ?TenantReferralProgramDraft
    {
        return TenantReferralProgramDraft::where('tenant_id', $tenantId)->where('status', 'draft')->latest('updated_at')->first();
    }

    public function save(string $tenantId, string $actor, array $data): TenantReferralProgramDraft
    {
        return DB::transaction(function () use ($tenantId, $actor, $data) {
            Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            $draft = app(ReferralProgramSetupService::class)->getOrCreateActiveDraft($tenantId, $actor);
            $config = $draft->config ?? [];
            $config['quick_start'] = $data;
            $draft->update(['config' => $config]);
            return $draft;
        });
    }

    public function publish(string $tenantId, string $actor, array $data): Program
    {
        return DB::transaction(function () use ($tenantId, $actor, $data) {
            $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();
            // Locking the tenant serializes double-clicks and concurrent admins.
            $previous = ProgramConnection::where('tenant_id', $tenantId)->first();
            if ($previous) return Program::forTenant($tenantId)->findOrFail($previous->program_id);
            if ($this->hasProgram($tenantId)) throw ValidationException::withMessages(['program' => 'This workspace already has a program. Open Programs to manage it.']);
            if (Program::forTenant($tenantId)->visible()->count() >= config('programs.max_programs_per_tenant', 25)) {
                throw ValidationException::withMessages(['program' => 'The workspace has reached its program limit.']);
            }
            $draft = $this->save($tenantId, $actor, $data);
            $program = Program::create([
                'tenant_id' => $tenantId, 'name' => $data['name'], 'program_type' => 'referral', 'operating_mode' => 'automated',
                'status' => 'draft', 'default_currency' => $data['currency'],
                'timezone' => $tenant->timezone ?? 'UTC', 'public_visibility' => 'private',
                'application_mode' => 'invite_only', 'approval_mode' => 'manual',
                'attribution_model' => 'code', 'attribution_window_days' => 30,
                'created_by' => $actor,
                'short_description' => 'Customer referrals for '.$data['website'],
            ]);
            $offer = ProgramOffer::create([
                'tenant_id' => $tenantId, 'program_id' => $program->id,
                'name' => $data['reward_scope'] === 'recurring' ? 'Subscription rewards' : 'First purchase reward',
                'status' => 'active', 'visibility' => 'public', 'created_by' => $actor,
            ]);
            $version = ProgramOfferVersion::create([
                'tenant_id' => $tenantId, 'program_id' => $program->id, 'offer_id' => $offer->id,
                'version_number' => 1, 'status' => 'published', 'currency' => $data['currency'],
                'reward_model' => $data['reward_model'], 'qualifying_event' => 'payment_received',
                'percentage_rate' => $data['reward_model'] === 'percentage' ? $data['reward_value'] : null,
                'fixed_amount' => $data['reward_model'] === 'fixed' ? $data['reward_value'] : null,
                'reward_rules' => [
                    'scope' => $data['reward_scope'], 'duration_months' => $data['duration_months'],
                    'hold_days' => $data['hold_days'], 'basis' => 'net_collected_excluding_tax',
                    'refund_policy' => 'reverse_reward', 'approval' => 'manual',
                    'self_referrals' => 'reject', 'existing_customers' => 'reject',
                ],
                'published_by' => $actor, 'published_at' => now(),
            ]);
            $offer->update(['current_version_id' => $version->id]);
            ProgramConnection::create([
                'tenant_id' => $tenantId, 'program_id' => $program->id, 'website' => $data['website'],
                'secret' => Str::random(64), 'status' => 'not_connected',
            ]);
            app(ProgramLifecycleService::class)->transition($program, 'active', $actor, 'tenant');
            $draft->update(['status' => 'published', 'published_at' => now()]);
            return $program->fresh();
        });
    }
}
