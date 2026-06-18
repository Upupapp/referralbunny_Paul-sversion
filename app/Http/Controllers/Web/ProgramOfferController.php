<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramOffer;
use App\Models\ProgramOfferVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manages offer creation/editing for the Programs V4 workspace "Offers" tab.
 *
 * An offer is created together with its first ProgramOfferVersion so it is
 * immediately usable; the richer reward-rules editor (tiers/milestones/
 * splits/holds/clawbacks) is deferred to a later phase.
 */
class ProgramOfferController extends Controller
{
    public function store(Request $request, string $tenantId, string $programId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('update', $program);

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'code'             => [
                'nullable', 'string', 'max:60',
                Rule::unique('program_offers', 'code')->where('program_id', $program->id),
            ],
            'visibility'       => ['sometimes', 'in:public,group_only,hidden'],
            'program_group_id' => ['nullable', 'string', 'exists:program_groups,id'],
            'reward_model'     => ['required', 'in:fixed,percentage'],
            'fixed_amount'     => ['required_if:reward_model,fixed', 'nullable', 'numeric', 'min:0'],
            'percentage_rate'  => ['required_if:reward_model,percentage', 'nullable', 'numeric', 'min:0', 'max:100'],
            'currency'         => ['nullable', 'string', 'size:3'],
        ]);

        $actorId = (string) (auth('tenant')->id() ?? auth('web')->id());

        DB::transaction(function () use ($data, $tenantId, $program, $actorId) {
            $offer = ProgramOffer::create([
                'tenant_id'        => $tenantId,
                'program_id'       => $program->id,
                'program_group_id' => $data['program_group_id'] ?? null,
                'name'             => $data['name'],
                'code'             => $data['code'] ?? null,
                'status'           => 'active',
                'visibility'       => $data['visibility'] ?? 'public',
                'created_by'       => $actorId,
                'updated_by'       => $actorId,
            ]);

            $version = ProgramOfferVersion::create([
                'tenant_id'        => $tenantId,
                'program_id'       => $program->id,
                'offer_id'         => $offer->id,
                'version_number'   => 1,
                'status'           => 'published',
                'currency'         => $data['currency'] ?? $program->default_currency,
                'reward_model'     => $data['reward_model'],
                'qualifying_event' => 'deal_closed',
                'fixed_amount'     => $data['fixed_amount'] ?? null,
                'percentage_rate'  => $data['percentage_rate'] ?? null,
                'published_by'     => $actorId,
                'published_at'     => now(),
            ]);

            $offer->update(['current_version_id' => $version->id]);
        });

        return back()->with('success', 'Offer created.');
    }

    public function update(Request $request, string $tenantId, string $programId, string $offerId)
    {
        abort_unless(config('programs.enabled'), 404);

        $program = Program::forTenant($tenantId)->findOrFail($programId);
        $this->authorize('update', $program);

        $offer = ProgramOffer::where('tenant_id', $tenantId)
            ->where('program_id', $program->id)
            ->findOrFail($offerId);

        $data = $request->validate([
            'name'       => ['sometimes', 'string', 'max:120'],
            'code'       => [
                'sometimes', 'nullable', 'string', 'max:60',
                Rule::unique('program_offers', 'code')->where('program_id', $program->id)->ignore($offer->id),
            ],
            'visibility' => ['sometimes', 'in:public,group_only,hidden'],
            'status'     => ['sometimes', 'in:active,inactive,archived'],
        ]);

        $data['updated_by'] = (string) (auth('tenant')->id() ?? auth('web')->id());

        $offer->update($data);

        return back()->with('success', 'Offer updated.');
    }
}
